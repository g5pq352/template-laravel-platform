(function () {
    window.uploaders = window.uploaders || {};
    window.globalCounter = window.globalCounter || 0;

    const demoImage = window.CMS_IMAGE_DEMO_URL || '/admin-assets/cms-crop/demo.jpg';

    function ensureCropModal() {
        const existingModal = document.getElementById('cropModal');
        if (existingModal) {
            if (existingModal.parentElement !== document.body) {
                document.body.appendChild(existingModal);
            }
            return;
        }

        const modal = document.createElement('div');
        modal.id = 'cropModal';
        modal.className = 'modal-crop';
        modal.innerHTML = `
            <div class="modal-content">
                <h2>圖片裁切</h2>
                <p>最低尺寸：<span id="minDimensions"></span> | 目前尺寸：<span id="currentDimensions" style="font-weight:bold;">0 x 0</span></p>
                <div class="img-container"><img id="imageToCrop" src="" alt=""></div>
                <div id="cropProgressMsg" style="display:none; text-align:center; color:#007bff; font-weight:bold; margin:10px 0;">圖片裁切處理中，請稍候...</div>
                <div style="text-align:center; margin-top:15px;">
                    <button id="confirmCropBtn" type="button" class="crop-action-btn btn-green">確認</button>
                    <button id="forceCropBtn" type="button" class="crop-action-btn btn-red">強制</button>
                    <button id="cancelCropBtn" type="button" class="crop-action-btn btn-gray">取消</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function showSwal(options) {
        if (window.Swal) {
            return window.Swal.fire(options);
        }
        window.alert(options.text || options.title || '操作失敗');
        return Promise.resolve();
    }

    class LaravelImageUploader {
        constructor(id, options) {
            this.id = id;
            this.prefix = options.prefix;
            this.minWidth = Number(options.minWidth || 0);
            this.minHeight = Number(options.minHeight || 0);
            this.outputWidth = Number(options.outputWidth || this.minWidth || 0);
            this.outputHeight = Number(options.outputHeight || this.minHeight || 0);
            this.maxSize = Number(options.maxSize || 2);
            this.aspectRatio = this.outputWidth > 0 && this.outputHeight > 0 ? this.outputWidth / this.outputHeight : null;
            this.croppedBlob = null;
            this.currentPreviewUrl = null;

            this.fileInput = document.getElementById(`${this.prefix}_${this.id}`);
            if (!this.fileInput) return;

            this.triggerBtn = document.querySelector(`.trigger-crop-btn[data-target="${this.fileInput.id}"]`);
            this.fileNameDisplay = document.getElementById(`fileNameDisplay${this.id}`);
            this.preview = document.getElementById(`croppedImagePreview${this.id}`);
            this.status = document.getElementById(`uploadStatus${this.id}`);
            this.imageUrlInput = document.getElementById(`imageUrl${this.id}`);
            this.removeBtn = document.getElementById(`remove_btn_${this.id}`) || document.getElementById(`removeBtn${this.id}`);
            this.titleInput = document.getElementById(`title_${this.id}`) || document.getElementById(`title_ex_${String(this.id).replace('ex_', '')}`);
            this.isExisting = String(this.id).startsWith('ex_');

            ensureCropModal();
            this.modal = document.getElementById('cropModal');
            this.imageToCrop = document.getElementById('imageToCrop');
            this.confirmBtn = document.getElementById('confirmCropBtn');
            this.forceBtn = document.getElementById('forceCropBtn');
            this.cancelBtn = document.getElementById('cancelCropBtn');
            this.minDimensionsSpan = document.getElementById('minDimensions');
            this.currentDimensionsSpan = document.getElementById('currentDimensions');
            this.progressMsg = document.getElementById('cropProgressMsg');
            this.cropper = null;

            this.bindEvents();
        }

        bindEvents() {
            if (this.triggerBtn) {
                this.triggerBtn.addEventListener('click', () => this.fileInput.click());
            }

            this.fileInput.addEventListener('change', (event) => {
                const file = event.target.files && event.target.files[0];
                if (file) this.handleFile(file);
            });

            if (this.removeBtn) {
                this.removeBtn.addEventListener('click', () => this.reset());
            }
        }

        handleFile(file) {
            const fileSizeMB = file.size / (1024 * 1024);
            if (fileSizeMB > this.maxSize) {
                showSwal({
                    icon: 'error',
                    title: '檔案太大',
                    text: `目前檔案大小為 ${fileSizeMB.toFixed(2)}MB，大小限制為 ${this.maxSize}MB。`
                });
                this.reset();
                return;
            }

            if (!file.type.startsWith('image/')) {
                showSwal({
                    icon: 'error',
                    title: '檔案格式錯誤',
                    text: '請選擇圖片檔案。'
                });
                this.reset();
                return;
            }

            if (this.fileNameDisplay) {
                if (this.fileNameDisplay.classList.contains('cms-image-file-name-hidden')) {
                    this.fileNameDisplay.style.display = 'none';
                } else {
                    this.fileNameDisplay.style.display = 'inline';
                    this.fileNameDisplay.textContent = file.name;
                }
            }
            if (this.status) this.status.textContent = '';

            const reader = new FileReader();
            reader.onload = (event) => this.openCropper(event.target.result);
            reader.onerror = () => {
                showSwal({
                    icon: 'error',
                    title: '圖片讀取失敗',
                    text: '請重新選擇圖片。'
                });
                this.reset();
            };
            reader.readAsDataURL(file);
        }

        openCropper(src) {
            if (!this.modal || !window.Cropper) {
                this.useOriginalPreview();
                return;
            }

            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }

            if (this.progressMsg) this.progressMsg.style.display = 'none';
            this.minDimensionsSpan.textContent = `${this.minWidth} x ${this.minHeight}` + (this.aspectRatio ? ` (比例 ${this.outputWidth}:${this.outputHeight})` : '');
            this.currentDimensionsSpan.textContent = '0 x 0';
            this.modal.style.display = 'flex';

            const initCropper = () => {
                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }

                this.cropper = new Cropper(this.imageToCrop, {
                    aspectRatio: this.aspectRatio || NaN,
                    viewMode: 1,
                    autoCropArea: 1,
                    responsive: true,
                    background: true,
                    crop: (event) => {
                        this.currentDimensionsSpan.textContent = `${Math.round(event.detail.width)} x ${Math.round(event.detail.height)}`;
                    }
                });

                if (this.status) this.status.textContent = '';
            };

            this.imageToCrop.onload = initCropper;
            this.imageToCrop.onerror = () => {
                showSwal({
                    icon: 'error',
                    title: '圖片讀取失敗',
                    text: '請重新選擇圖片檔案。'
                });
                this.closeCropper(false);
            };

            this.imageToCrop.src = '';
            this.imageToCrop.src = src;

            if (this.imageToCrop.complete && this.imageToCrop.naturalWidth > 0) {
                initCropper();
            }

            this.confirmBtn.onclick = () => this.confirmCrop(false);
            this.forceBtn.onclick = () => this.confirmCrop(true);
            this.cancelBtn.onclick = () => this.closeCropper(false);
        }

        confirmCrop(force) {
            if (!this.cropper) {
                showSwal({
                    icon: 'warning',
                    title: '圖片尚未載入完成',
                    text: '請稍候再確認。'
                });
                return;
            }

            const data = this.cropper.getData(true);
            if (!force && (data.width < this.minWidth || data.height < this.minHeight)) {
                showSwal({
                    icon: 'warning',
                    title: '裁切尺寸不足',
                    text: `建議裁切尺寸至少為 ${this.minWidth} x ${this.minHeight}px，也可以使用強制裁切。`
                });
                return;
            }

            if (this.progressMsg) this.progressMsg.style.display = 'block';

            const canvasOptions = {};
            if (this.outputWidth > 0 && this.outputHeight > 0) {
                canvasOptions.width = this.outputWidth;
                canvasOptions.height = this.outputHeight;
            }

            const canvas = this.cropper.getCroppedCanvas(canvasOptions);
            if (!canvas) {
                showSwal({
                    icon: 'error',
                    title: '裁切失敗',
                    text: '無法產生裁切圖片，請重新選擇圖片。'
                });
                if (this.progressMsg) this.progressMsg.style.display = 'none';
                return;
            }

            canvas.toBlob((blob) => {
                if (!blob) {
                    showSwal({
                        icon: 'error',
                        title: '裁切失敗',
                        text: '無法產生裁切圖片，請重新選擇圖片。'
                    });
                    if (this.progressMsg) this.progressMsg.style.display = 'none';
                    return;
                }

                this.croppedBlob = blob;
                if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
                this.currentPreviewUrl = URL.createObjectURL(blob);

                if (this.preview) {
                    this.preview.src = this.currentPreviewUrl;
                    this.preview.dataset.cmsPlaceholder = '0';
                    this.preview.classList.add('cms-image-lightbox-trigger');
                }

                if (this.status) this.status.textContent = '';
                if (this.removeBtn) this.removeBtn.style.display = 'inline-block';
                this.closeCropper(true);
            }, 'image/jpeg', 0.92);
        }

        useOriginalPreview() {
            const file = this.fileInput.files && this.fileInput.files[0];
            if (!file) return;

            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
            this.currentPreviewUrl = URL.createObjectURL(file);

            if (this.preview) {
                this.preview.src = this.currentPreviewUrl;
                this.preview.dataset.cmsPlaceholder = '0';
                this.preview.classList.add('cms-image-lightbox-trigger');
            }
            if (this.status) this.status.textContent = '';
        }

        closeCropper(keepFile) {
            if (this.progressMsg) this.progressMsg.style.display = 'none';
            if (this.modal) this.modal.style.display = 'none';

            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }

            if (!keepFile && !this.croppedBlob) {
                this.reset();
            }
        }

        reset() {
            this.croppedBlob = null;
            this.fileInput.value = '';

            if (this.fileNameDisplay) {
                this.fileNameDisplay.style.display = 'none';
                this.fileNameDisplay.textContent = '未選擇';
            }
            if (this.imageUrlInput) this.imageUrlInput.value = '';

            if (this.status) this.status.textContent = '';
            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);

            if (this.preview) {
                this.preview.src = demoImage;
                this.preview.dataset.cmsPlaceholder = '1';
                this.preview.classList.remove('cms-image-lightbox-trigger');
            }

            if (this.removeBtn) this.removeBtn.style.display = 'none';
            if (this.titleInput && !this.isExisting) this.titleInput.value = '';

            const subfield = this.fileInput.closest('.dynamic-subfield');
            subfield?.querySelector('input[name$="[_existing]"]')?.remove();

            if (this.isExisting) {
                const fileId = String(this.id).replace('ex_', '');
                markImageForDeletion(fileId);
            }
        }

        applyCroppedFile() {
            if (!this.croppedBlob || !this.fileInput || !document.body.contains(this.fileInput)) return;

            const file = new File([this.croppedBlob], `${this.prefix}_${this.id}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now()
            });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            this.fileInput.files = dataTransfer.files;
        }
    }

    window.LaravelImageUploader = LaravelImageUploader;

    function deleteDynamicImageRow(id) {
        showSwal({
            title: '確定刪除?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '刪除',
            cancelButtonText: '取消'
        }).then((result) => {
            if (!result.isConfirmed) return;

            document.getElementById(`row_container_${id}`)?.remove();
            delete window.uploaders[id];
        });
    }

    window.addDynamicField = function (containerId, prefix, configKey, isRemovable = true) {
        const container = document.getElementById(containerId);
        const template = document.getElementById('universalRowTemplate');
        const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[configKey]) || { IW: 800, IH: 600, OW: 800, OH: 600 };
        if (!container || !template) return;

        if (isRemovable) {
            const currentGroupInputs = container.querySelectorAll('.hidden-file-input');
            if (currentGroupInputs.length > 0) {
                const lastInput = currentGroupInputs[currentGroupInputs.length - 1];
                const match = lastInput.id.match(/_(\d+)$/);
                if (match) {
                    const lastUploaderId = parseInt(match[1], 10);
                    const lastUploader = window.uploaders[lastUploaderId];

                    if (lastUploader && !lastUploader.croppedBlob) {
                        showSwal({ icon: 'warning', title: '無法新增', text: '請先完成上一張圖片' });
                        return;
                    }
                }
            }
        }

        window.globalCounter += 1;
        const id = window.globalCounter;
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('tr');
        row.id = `row_container_${id}`;

        const uniqueKey = `${prefix}_${id}`;
        const fileInput = clone.querySelector('.hidden-file-input');
        fileInput.id = uniqueKey;
        fileInput.name = `${prefix}[]`;
        fileInput.classList.add('new-image-input');

        clone.querySelector('.trigger-crop-btn').dataset.target = uniqueKey;
        clone.querySelector('.preview-img').id = `croppedImagePreview${id}`;
        clone.querySelector('.file-name-display').id = `fileNameDisplay${id}`;
        clone.querySelector('.status-msg').id = `uploadStatus${id}`;
        clone.querySelector('.url-input').id = `imageUrl${id}`;

        const titleInput = clone.querySelector('.title-input');
        titleInput.id = `title_${id}`;
        titleInput.name = `${prefix}_title[]`;

        const removeBtn = clone.querySelector('.delete-row-btn');
        removeBtn.id = `removeBtn${id}`;

        const dragHandle = clone.querySelector('.drag-handle');
        if (container.dataset.multiple === '1') dragHandle.style.display = 'block';

        const trashBtn = clone.querySelector('.trash-btn');
        if (!isRemovable) {
            trashBtn.style.display = 'none';
        } else {
            trashBtn.onclick = () => deleteDynamicImageRow(id);
        }

        container.appendChild(clone);
        window.uploaders[id] = new LaravelImageUploader(id, {
            prefix,
            minWidth: cfg.IW,
            minHeight: cfg.IH,
            outputWidth: cfg.OW || cfg.IW,
            outputHeight: cfg.OH || cfg.IH,
            maxSize: container.dataset.maxSize || 2
        });
    };

    function openCmsImageLightbox(src, title) {
        if (!src || src === demoImage) return;

        if (window.jQuery && window.jQuery.magnificPopup) {
            window.jQuery.magnificPopup.open({
                items: { src, title: title || '' },
                type: 'image',
                closeOnContentClick: true,
                closeBtnInside: false,
                mainClass: 'mfp-with-zoom',
                image: {
                    verticalFit: true,
                    titleSrc: 'title'
                }
            });
            return;
        }

        window.open(src, '_blank', 'noopener');
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.fancyboxImg, .cms-image-lightbox-trigger');
        if (!trigger) return;

        const image = trigger.matches('img') ? trigger : trigger.querySelector('img');
        if (image && image.dataset.cmsPlaceholder === '1') {
            event.preventDefault();
            return;
        }

        const src = trigger.getAttribute('href') || image?.currentSrc || image?.src;
        if (!src) return;

        event.preventDefault();
        openCmsImageLightbox(src, trigger.getAttribute('title') || image?.getAttribute('title') || image?.getAttribute('alt') || '');
    });

    window.markImageForDeletion = function (fileId) {
        if (!fileId || document.getElementById(`del_input_${fileId}`)) return;
        const container = document.getElementById('delete_file_container');
        if (!container) return;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_file[]';
        input.value = fileId;
        input.id = `del_input_${fileId}`;
        container.appendChild(input);
    };

    window.deleteImageItem = function (fileId) {
        showSwal({
            title: '確定要刪除圖片？',
            text: '刪除後需要儲存才會正式生效。',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '刪除',
            cancelButtonText: '取消'
        }).then((result) => {
            if (!result.isConfirmed) return;
            document.getElementById(`img_item_${fileId}`)?.remove();
            markImageForDeletion(fileId);
            delete window.uploaders[`ex_${fileId}`];
        });
    };

    function hasSelectedFile(input) {
        return !!(input && input.files && input.files.length > 0);
    }

    function hasExistingDynamicFile(input) {
        const subfield = input.closest('.dynamic-subfield');
        return !!subfield?.querySelector('input[name$="[_existing]"]')?.value;
    }

    function hasRealPreview(container) {
        return [...container.querySelectorAll('img')].some((image) => {
            return image.dataset.cmsPlaceholder !== '1'
                && image.src
                && !image.src.includes('/admin-assets/cms-crop/demo.jpg');
        });
    }

    function dynamicRowHasValue(row) {
        const controls = row.querySelectorAll('input, textarea, select');
        return [...controls].some((control) => {
            if (control.name && control.name.endsWith('[_uid]')) return false;
            if (control.type === 'file') return hasSelectedFile(control);
            if (control.type === 'hidden' && control.name && control.name.endsWith('[_existing]')) return !!control.value;
            if (control.type === 'hidden') return false;
            return String(control.value || '').trim() !== '';
        }) || hasRealPreview(row);
    }

    function activateContainingTab(element) {
        const pane = element.closest('.tab-pane');
        if (!pane || pane.classList.contains('active')) return;
        const trigger = document.querySelector(`[data-bs-target="#${pane.id}"]`);
        if (trigger && window.bootstrap?.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(trigger).show();
        } else {
            trigger?.click();
        }
    }

    function expandContainingDynamicRow(element) {
        const row = element.closest('[data-dynamic-row]');
        if (!row || !row.classList.contains('collapsed')) return;

        row.classList.remove('collapsed');
        const icon = row.querySelector('[data-dynamic-toggle] i');
        if (icon) {
            icon.className = 'fas fa-chevron-up';
        }
    }

    function tabNameForElement(element) {
        const pane = element.closest('.tab-pane');
        if (!pane) return '目前分頁';

        const trigger = document.querySelector(`[data-bs-target="#${pane.id}"]`);
        return (trigger?.textContent || '目前分頁').replace(/\s+/g, ' ').trim();
    }

    function labelForControl(control) {
        if (control.dataset.fieldLabel) return control.dataset.fieldLabel;

        const subfield = control.closest('.dynamic-subfield');
        const subfieldLabel = subfield?.querySelector('label')?.textContent;
        if (subfieldLabel) return subfieldLabel.replace('*', '').trim();

        const formGroup = control.closest('.form-group, [data-dynamic-row]');
        const formGroupLabel = formGroup?.querySelector('label.control-label, label')?.textContent;
        if (formGroupLabel) return formGroupLabel.replace('*', '').trim();

        return control.getAttribute('name') || '欄位';
    }

    function errorMessage(element, message) {
        return {
            element,
            tab: tabNameForElement(element),
            message
        };
    }

    function validateAdminRequiredFields(form) {
        const errors = [];

        form.querySelectorAll('input[required], textarea[required], select[required]').forEach((control) => {
            if (control.disabled) return;
            if (control.type === 'hidden') return;
            if (control.type === 'file' && control.dataset.requiredUpload === '1') return;

            const valid = typeof control.checkValidity === 'function'
                ? control.checkValidity()
                : String(control.value || '').trim() !== '';

            if (!valid) {
                const label = labelForControl(control);
                errors.push(errorMessage(control, `${label}為必填欄位。`));
            }
        });

        form.querySelectorAll('.draggable_image[data-required="1"]').forEach((container) => {
            const label = container.dataset.fieldLabel || '圖片';
            const hasUpload = [...container.querySelectorAll('input[type="file"]')].some(hasSelectedFile);
            const hasDropzoneUpload = container.dataset.dropzoneUploaded === '1';
            if (!hasUpload && !hasRealPreview(container) && !hasDropzoneUpload) {
                errors.push(errorMessage(container, `${label}為必填欄位，請先上傳圖片。`));
            }
        });

        form.querySelectorAll('[data-dynamic-wrapper][data-required="1"]').forEach((wrapper) => {
            const label = wrapper.dataset.fieldLabel || '連續資料';
            const hasRows = [...wrapper.querySelectorAll('[data-dynamic-row]')].some(dynamicRowHasValue);
            if (!hasRows) {
                errors.push(errorMessage(wrapper, `${label}至少需要新增一個項目。`));
            }
        });

        form.querySelectorAll('input[data-required-upload="1"]').forEach((input) => {
            const label = input.dataset.fieldLabel || '檔案';
            if (!hasSelectedFile(input) && !hasExistingDynamicFile(input)) {
                errors.push(errorMessage(input, `${label}為必填欄位，請先上傳檔案。`));
            }
        });

        return errors;
    }

    function revealRequiredError(element, behavior = 'auto') {
        activateContainingTab(element);
        expandContainingDynamicRow(element);
    }

    function showRequiredErrors(errors) {
        if (errors.length === 0) return;

        const first = errors[0];
        revealRequiredError(first.element);

        const list = errors.slice(0, 8).map((error) => (
            `<div style="text-align:left;margin-bottom:4px;"><b>${error.tab}</b>：${error.message}</div>`
        )).join('');
        const more = errors.length > 8 ? `<div style="text-align:left;color:#777;">另有 ${errors.length - 8} 個欄位未完成。</div>` : '';

        showSwal({
            icon: 'warning',
            title: '請確認必填欄位',
            html: list + more,
            confirmButtonText: '確認'
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.classList.contains('ecommerce-form')) return;

        Object.values(window.uploaders).forEach((uploader) => uploader.applyCroppedFile());

        const errors = validateAdminRequiredFields(form);
        if (errors.length === 0) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        showRequiredErrors(errors);
    }, true);

    document.addEventListener('click', function (event) {
        const submitter = event.target.closest('button[type="submit"], input[type="submit"]');
        if (!submitter) return;

        const form = submitter.form || submitter.closest('form');
        if (!form || !form.classList.contains('ecommerce-form')) return;

        Object.values(window.uploaders).forEach((uploader) => uploader.applyCroppedFile());

        const errors = validateAdminRequiredFields(form);
        if (errors.length === 0) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        showRequiredErrors(errors);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.draggable_image').forEach((container) => {
            if (window.Sortable && container.dataset.multiple === '1') {
                Sortable.create(container, {
                    animation: 100,
                    handle: '.drag-handle',
                    dataIdAttr: 'data-id',
                    ghostClass: 'ryder-ghost',
                    chosenClass: 'ryder-chosen'
                });
            }
        });
    });
})();
