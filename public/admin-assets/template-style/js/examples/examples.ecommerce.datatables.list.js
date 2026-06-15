/*
Name: 			eCommerce / eCommerce DataTable List - Examples
Written by: 	Okler Themes - (http://www.okler.net)
Theme Version: 	4.0.0
*/

(function($) {

	'use strict';

	/*
	* eCommerce DataTable List
	*/
	var ecommerceListDataTableInit = function() {

		var $ecommerceListTable = $('#datatable-ecommerce-list');

		$ecommerceListTable.dataTable({
			dom: '<"row justify-content-between"<"col-auto"><"col-auto">><"table-responsive"t>ip',
			columnDefs: [
				{
					targets: 0,
					orderable: false
				}
			],
			paging: false,   // 關閉 JS 分頁，讓 PHP (display_page.php) 的傳統分頁按鈕接管
			info: false,     // 關閉左下角的「顯示第 1 至 12 項結果」，避免與 PHP 分頁衝突
			bSort: false,    // 關閉 JS 排序，因為 JS 只會排序當下這 20 筆，沒有意義
			pageLength: 12,
			order: [],
			language: {
				paginate: {
					previous: '<i class="fas fa-chevron-left"></i>',
					next: '<i class="fas fa-chevron-right"></i>'
				}
			},
			drawCallback: function() {
				
				// Move dataTables info to footer of table
				$ecommerceListTable
					.closest('.dataTables_wrapper')
					.find('.dataTables_info')
					.appendTo( $ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.results-info-wrapper') );

				// Move dataTables pagination to footer of table
				$ecommerceListTable
					.closest('.dataTables_wrapper')
					.find('.dataTables_paginate')
					.appendTo( $ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.pagination-wrapper') );
				
				$ecommerceListTable.closest('.datatables-header-footer-wrapper').find('.pagination').addClass('pagination-modern pagination-modern-spacing justify-content-center');

			}
		});

		// Link "Show" select for change the "pageLength" of dataTable
		$(document).on('change', '.results-per-page', function(){
			var $this = $(this);
			var url = new URL(window.location.href);
			url.searchParams.set('maxRows', $this.val());
			url.searchParams.set('pageNum', 0); // 回到第一頁
			window.location.href = url.toString();
		});

		// Function to perform server-side search
		var performSearch = function() {
			var $searchField = $('.search-term');
			var url = new URL(window.location.href);
			if ($searchField.val().trim() !== '') {
				url.searchParams.set('search', $searchField.val().trim());
			} else {
				url.searchParams.delete('search');
			}
			url.searchParams.set('pageNum', 0); // 回到第一頁
			window.location.href = url.toString();
		};

		// Link "Search" field to trigger server-side search on Enter key
		$(document).on('keyup', '.search-term', function(e){
			if(e.key === 'Enter' || e.keyCode === 13) {
				performSearch();
			}
		});

		// Trigger search on button click
		$(document).on('click', '.search-button', function(){
			performSearch();
		});

		// Trigger search when "filter-by" changes (keep existing behavior, but server-side if needed)
		$(document).on('change', '.filter-by', function(){
			// 如果分類改變，原本會觸發搜尋，現在不需要了，因為分類改變本身就會 reload 頁面
		});

		// Select All
		$ecommerceListTable.find( '.select-all' ).on('change', function(){
			if( this.checked ) {
				$ecommerceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', true);
			} else {
				$ecommerceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', false);
			}
		})

	};

	ecommerceListDataTableInit();

}(jQuery));