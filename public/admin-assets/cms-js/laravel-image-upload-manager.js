(function () {
    window.uploaders = window.uploaders || {};
    window.globalCounter = window.globalCounter || 0;

    const demoImage = window.CMS_IMAGE_DEMO_URL || '/admin-assets/cms-crop/demo.jpg';

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
            this.removeBtn = document.getElementById(`remove_btn_${this.id}`) || document.getElementById(`removeBtn${this.id}`);
            this.titleInput = document.getElementById(`title_${this.id}`) || document.getElementById(`title_ex_${String(this.id).replace('ex_', '')}`);
            this.isExisting = String(this.id).startsWith('ex_');

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
                Swal.fire({
                    icon: 'error',
                    title: '檔案太大了',
                    text: `該圖片大小為 ${fileSizeMB.toFixed(2)}MB，超過了限制的 ${this.maxSize}MB。`
                });
                this.reset();
                return;
            }

            if (this.fileNameDisplay) {
                this.fileNameDisplay.style.display = 'inline';
                this.fileNameDisplay.textContent = file.name;
            }
            if (this.status) this.status.textContent = '圖片載入中...';

            const reader = new FileReader();
            reader.onload = (event) => this.openCropper(event.target.result);
            reader.readAsDataURL(file);
        }

        openCropper(src) {
            if (!this.modal || !window.Cropper) {
                this.useOriginalPreview();
                return;
            }

            if (this.cropper) {
                this.cropper.destroy();
            }

            this.imageToCrop.src = src;
            this.minDimensionsSpan.textContent = `${this.minWidth} x ${this.minHeight}` + (this.aspectRatio ? ` (比例 ${this.outputWidth}:${this.outputHeight})` : ' (比例自由)');
            this.currentDimensionsSpan.textContent = '0 x 0';
            this.modal.style.display = 'flex';

            this.imageToCrop.onload = () => {
                this.cropper = new Cropper(this.imageToCrop, {
                    aspectRatio: this.aspectRatio || NaN,
                    viewMode: 1,
                    autoCropArea: 1,
                    crop: (event) => {
                        this.currentDimensionsSpan.textContent = `${Math.round(event.detail.width)} x ${Math.round(event.detail.height)}`;
                    }
                });
            };

            this.confirmBtn.onclick = () => this.confirmCrop(false);
            this.forceBtn.onclick = () => this.confirmCrop(true);
            this.cancelBtn.onclick = () => this.closeCropper(false);
        }

        confirmCrop(force) {
            if (!this.cropper) return;

            const data = this.cropper.getData(true);
            if (!force && (data.width < this.minWidth || data.height < this.minHeight)) {
                Swal.fire({
                    icon: 'warning',
                    title: '圖片尺寸不足',
                    text: `請至少裁切 ${this.minWidth} x ${this.minHeight}px，或使用強制。`
                });
                return;
            }

            if (this.progressMsg) this.progressMsg.style.display = 'block';
            const canvasOptions = {};
            if (this.outputWidth > 0 && this.outputHeight > 0) {
                canvasOptions.width = this.outputWidth;
                canvasOptions.height = this.outputHeight;
            }

            this.cropper.getCroppedCanvas(canvasOptions).toBlob((blob) => {
                this.croppedBlob = blob;
                if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
                this.currentPreviewUrl = URL.createObjectURL(blob);
                if (this.preview) {
                    this.preview.src = this.currentPreviewUrl;
                    this.preview.dataset.cmsPlaceholder = '0';
                    this.preview.classList.add('cms-image-lightbox-trigger');
                }
                if (this.status) this.status.textContent = '已裁切，儲存後生效';
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
            if (this.status) this.status.textContent = '已選擇，儲存後生效';
        }

        closeCropper(keepFile) {
            if (this.progressMsg) this.progressMsg.style.display = 'none';
            if (this.modal) this.modal.style.display = 'none';
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            if (!keepFile && !this.croppedBlob) this.reset();
        }

        reset() {
            this.croppedBlob = null;
            this.fileInput.value = '';
            if (this.fileNameDisplay) {
                this.fileNameDisplay.style.display = 'none';
                this.fileNameDisplay.textContent = '未選擇';
            }
            if (this.status) this.status.textContent = '';
            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
            if (this.preview) {
                this.preview.src = demoImage;
                this.preview.dataset.cmsPlaceholder = '1';
            }
            if (this.removeBtn) this.removeBtn.style.display = 'none';
            if (this.titleInput && !this.isExisting) this.titleInput.value = '';
            if (this.isExisting) {
                const fileId = String(this.id).replace('ex_', '');
                markImageForDeletion(fileId);
            }
        }

        applyCroppedFile() {
            if (!this.croppedBlob || !this.fileInput || !document.body.contains(this.fileInput)) return;
            const file = new File([this.croppedBlob], `${this.prefix}_${this.id}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            this.fileInput.files = dataTransfer.files;
        }
    }

    window.LaravelImageUploader = LaravelImageUploader;

    window.addDynamicField = function (containerId, prefix, configKey, isRemovable = true) {
        const container = document.getElementById(containerId);
        const template = document.getElementById('universalRowTemplate');
        const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[configKey]) || { IW: 800, IH: 600, OW: 800, OH: 600 };
        if (!container || !template) return;

        window.globalCounter += 1;
        const id = window.globalCounter;
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('.image-manage-item');
        item.id = `row_container_${id}`;
        item.dataset.id = `new_${id}`;

        const uniqueKey = `${prefix}_${id}`;
        const fileInput = clone.querySelector('.hidden-file-input');
        fileInput.id = uniqueKey;
        fileInput.name = `${prefix}[]`;

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
            trashBtn.onclick = () => {
                document.getElementById(`row_container_${id}`)?.remove();
                delete window.uploaders[id];
            };
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
                items: {
                    src,
                    title: title || ''
                },
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
        Swal.fire({
            title: '確定刪除?',
            text: '此操作將會刪除這張圖片',
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

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.classList.contains('ecommerce-form')) return;
        Object.values(window.uploaders).forEach((uploader) => uploader.applyCroppedFile());
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
