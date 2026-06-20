/**
 * CPT & ACF Export/Import Page JavaScript
 *
 * Mirrors admin.js + admin-batch.js patterns exactly:
 * - Export: selective panel with load-more (300/batch-setting per page),
 *   chunked export splitting into multiple JSON files (postsPerFile),
 *   batch mode via PHP batch session
 * - Import: multi-file support, file tracker UI, per-post status gear modal,
 *   retry failed posts, regular sequential mode + batch concurrent mode
 *
 * Localized via `peiwm_cpt_acf` object:
 * {ajax_url, nonce, is_pro_active, batch_mode, batch_size, concurrent_requests,
 *  export_json_size, batch_delay, strings}
 *
 * @package Post_Export_Import_With_Media
 * @since   1.5.0
 */

( function ( $ ) {
	'use strict';

	var cfg   = window.peiwm_cpt_acf || {};
	var s     = cfg.strings || {};
	var isBatch = cfg.batch_mode === '1';

	// =========================================================================
	// UTILITY HELPERS
	// =========================================================================

	function updateProgress( $wrapper, pct, msg ) {
		$wrapper.show();
		$wrapper.find( '.peiwm-progress-fill' ).css( 'width', Math.min( pct, 100 ) + '%' );
		$wrapper.find( '.peiwm-progress-text' ).text( msg );
	}

	function appendLog( $wrapper, msg, isError ) {
		var cls = isError ? 'peiwm-log-item peiwm-log-error' : 'peiwm-log-item';
		var $entry = $( '<div class="' + cls + '">' + msg + '</div>' );
		$wrapper.find( '.peiwm-log' ).append( $entry );
		// Auto-scroll
		var $log = $wrapper.find( '.peiwm-log' );
		$log.scrollTop( $log.prop( 'scrollHeight' ) );
	}

	function triggerJsonDownload( json, filename ) {
		var blob = new Blob( [ json ], { type: 'application/json' } );
		var url  = URL.createObjectURL( blob );
		var a    = document.createElement( 'a' );
		a.href   = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	}

	function getSelectedIds( $list ) {
		var ids = [];
		$list.find( 'input[type=checkbox]:checked' ).each( function () {
			var val = $( this ).val();
			if ( val ) { ids.push( val ); }
		} );
		return ids;
	}

	function updateSelectedCount( $count, $list ) {
		var n = $list.find( 'input[type=checkbox]:checked' ).length;
		$count.text( n + ' selected' );
	}

	/**
	 * Return comma-separated selected ACF field keys, or empty string for "all fields".
	 */
	function getSelectedAcfFieldKeys() {
		if ( ! $( '#peiwm-cpt-export-acf-fields' ).is( ':checked' ) ) {
			return '';
		}
		var $picker = $( '#peiwm-cpt-acf-field-picker' );
		if ( ! $picker.is( ':visible' ) ) {
			return ''; // picker hidden = export all
		}
		var keys = [];
		$picker.find( '.peiwm-acf-field-checkbox:checked' ).each( function () {
			keys.push( $( this ).val() );
		} );
		// If all are checked (or none visible), treat as "export all"
		var total   = $picker.find( '.peiwm-acf-field-checkbox' ).length;
		var checked = keys.length;
		return ( checked === 0 || checked === total ) ? '' : keys.join( ',' );
	}

	// =========================================================================
	// MODAL UTILITIES
	// =========================================================================

	function showModal( type, title, message ) {
		$( '.peiwm-modal-overlay' ).removeClass( 'peiwm-show' ).hide();
		$( document ).off( 'keydown.peiwm-modal' );

		var modalId = '#peiwm-modal-overlay';
		var modalClass = '';

		switch ( type ) {
			case 'success':
				modalId = '#peiwm-success-modal';
				modalClass = 'peiwm-success-modal';
				break;
			case 'error':
				modalId = '#peiwm-error-modal';
				modalClass = 'peiwm-error-modal';
				break;
			case 'warning':
				modalId = '#peiwm-modal-overlay';
				modalClass = 'peiwm-warning-modal';
				break;
			case 'danger':
				modalId = '#peiwm-modal-overlay';
				modalClass = 'peiwm-danger-modal';
				break;
		}

		var $modal = $( modalId );
		var $modalContent = $modal.find( '.peiwm-modal' );

		$modal.find( '.peiwm-modal-header h3' ).text( title );
		$modal.find( '.peiwm-modal-body p' ).html( message );

		if ( type === 'warning' || type === 'danger' ) {
			$modalContent.addClass( modalClass );
		} else {
			$modalContent.removeClass( 'peiwm-warning-modal peiwm-danger-modal' );
		}

		var $confirmBtn = $modal.find( '#peiwm-modal-confirm' );
		var $cancelBtn = $modal.find( '#peiwm-modal-cancel' );
		$confirmBtn.off( 'click' );
		$cancelBtn.off( 'click' );

		if ( type === 'warning' || type === 'danger' ) {
			return new Promise( function ( resolve, reject ) {
				$confirmBtn.on( 'click', function () {
					hideModal( modalId );
					resolve();
				} );
				$cancelBtn.on( 'click', function () {
					hideModal( modalId );
					reject();
				} );
				$modal.show().addClass( 'peiwm-show' );
				$modal.find( '.peiwm-modal-close' ).off( 'click' ).on( 'click', function () {
					hideModal( modalId );
					reject();
				} );
				$modal.off( 'click' ).on( 'click', function ( e ) {
					if ( e.target === this ) {
						hideModal( modalId );
						reject();
					}
				} );
				$( document ).off( 'keydown.peiwm-modal' ).on( 'keydown.peiwm-modal', function ( e ) {
					if ( e.key === 'Escape' ) {
						hideModal( modalId );
						reject();
					}
				} );
			} );
		} else {
			$modal.show().addClass( 'peiwm-show' );
			$modal.find( '.peiwm-modal-close' ).off( 'click' ).on( 'click', function () {
				hideModal( modalId );
			} );
			$modal.off( 'click' ).on( 'click', function ( e ) {
				if ( e.target === this ) {
					hideModal( modalId );
				}
			} );
			$( document ).off( 'keydown.peiwm-modal' ).on( 'keydown.peiwm-modal', function ( e ) {
				if ( e.key === 'Escape' ) {
					hideModal( modalId );
				}
			} );
		}
	}

	function hideModal( modalId ) {
		var $modal = $( modalId );
		$modal.removeClass( 'peiwm-show' );
		setTimeout( function () {
			$modal.hide();
		}, 300 );
	}

	function showError( message ) {
		showModal( 'error', s.error || 'Error', message );
	}

	function showSuccess( message ) {
		showModal( 'success', 'Success!', message );
	}

	// =========================================================================
	// STATE
	// =========================================================================

	var importedPostsData = [];  // Flat merged array of all posts from all files (for selective list)
	var importedFilesData = [];  // Array of arrays — one per file (for import processing)
	var importedFileNames = [];  // File names parallel to importedFilesData

	// ACF field picker state
	var acfFieldsLoaded   = '';  // Last CPT name for which fields were loaded (cache key)
	var acfFieldsCache    = [];  // Cached groups from the last load

	// =========================================================================
	// PAGE READY
	// =========================================================================

	$( document ).ready( function () {

		// =========================================================================
		// PREMIUM MODAL LISTENER (copied from admin.js since admin.js is not loaded here)
		// =========================================================================
		$( document ).on( 'click', '.peiwm-open-premium-modal, .peiwm-locked-section', function ( e ) {
			// Don't trigger if clicking a real interactive element inside
			if ( $( e.target ).is( 'input, select, textarea, button:not(.peiwm-open-premium-modal), label, a' ) ) return;
			e.preventDefault();
			e.stopPropagation();
			var $modal = $( '#peiwm-premium-modal' );
			$modal.show().addClass( 'peiwm-show' );
			$modal.find( '.peiwm-premium-close, .peiwm-modal-close' ).off( 'click' ).on( 'click', function () {
				$modal.removeClass( 'peiwm-show' ).hide();
			} );
			$modal.off( 'click.premium' ).on( 'click.premium', function ( ev ) {
				if ( ev.target === this ) $modal.removeClass( 'peiwm-show' ).hide();
			} );
			$( document ).off( 'keydown.premium-modal' ).on( 'keydown.premium-modal', function ( ev ) {
				if ( ev.key === 'Escape' ) $modal.removeClass( 'peiwm-show' ).hide();
			} );
		} );

		loadCptList();

		// --- Export: show/hide selective panel when CPT or checkbox changes ---
		$( '#peiwm-cpt-select' ).on( 'change', function () {
			var cpt = $( this ).val();
			if ( cpt && $( '#peiwm-cpt-export-selective' ).is( ':checked' ) ) {
				loadExportPostsList( cpt, 0, false );
			}
			// Load ACF field picker for the selected CPT
			if ( cpt && $( '#peiwm-cpt-export-acf-fields' ).is( ':checked' ) ) {
				loadAcfFieldPicker( cpt );
			} else {
				$( '#peiwm-cpt-acf-field-picker' ).hide();
			}
		} );

		// --- Toggle ACF field picker when ACF checkbox changes ---
		$( '#peiwm-cpt-export-acf-fields' ).on( 'change', function () {
			var cpt = $( '#peiwm-cpt-select' ).val();
			if ( $( this ).is( ':checked' ) && cpt ) {
				loadAcfFieldPicker( cpt );
			} else {
				$( '#peiwm-cpt-acf-field-picker' ).hide();
			}
		} );

		// --- ACF field search ---
		$( '#peiwm-cpt-acf-field-search' ).on( 'keyup', function () {
			var q = $( this ).val().toLowerCase();
			$( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-item' ).each( function () {
				var label = $( this ).data( 'label' ) || '';
				var name  = $( this ).data( 'name' )  || '';
				$( this ).toggle( label.toLowerCase().indexOf( q ) !== -1 || name.toLowerCase().indexOf( q ) !== -1 );
			} );
			updateAcfFieldCount();
		} );

		// --- ACF field select/deselect all ---
		$( '#peiwm-cpt-acf-select-all-fields' ).on( 'click', function () {
			$( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-checkbox:visible' ).prop( 'checked', true );
			// Also check hidden ones (select truly all)
			$( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-checkbox' ).prop( 'checked', true );
			updateAcfFieldCount();
		} );

		$( '#peiwm-cpt-acf-deselect-all-fields' ).on( 'click', function () {
			$( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-checkbox' ).prop( 'checked', false );
			updateAcfFieldCount();
		} );

		// --- Export All CPT Types button ---
		$( document ).on( 'click', '#peiwm-export-all-cpts', function () {
			var exportAcf = $( '#peiwm-cpt-export-acf-fields' ).is( ':checked' ) ? '1' : '0';
			startExportAllCpts( exportAcf );
		} );

		$( '#peiwm-cpt-export-selective' ).on( 'change', function () {
			var cpt = $( '#peiwm-cpt-select' ).val();
			if ( $( this ).is( ':checked' ) ) {
				if ( ! cpt ) {
					showError( s.select_post_type );
					$( this ).prop( 'checked', false );
					return;
				}
				$( '#peiwm-cpt-export-selective-panel' ).show();
				loadExportPostsList( cpt, 0, false );
			} else {
				$( '#peiwm-cpt-export-selective-panel' ).hide();
			}
		} );

		// Export: select all
		$( '#peiwm-cpt-export-select-all' ).on( 'change', function () {
			$( '#peiwm-cpt-export-posts-list input[type=checkbox]' ).prop( 'checked', $( this ).is( ':checked' ) );
			updateSelectedCount( $( '#peiwm-cpt-export-selected-count' ), $( '#peiwm-cpt-export-posts-list' ) );
		} );

		// Export: search
		var exportSearchTimer;
		$( '#peiwm-cpt-export-search' ).on( 'keyup', function () {
			clearTimeout( exportSearchTimer );
			var self = this;
			exportSearchTimer = setTimeout( function () {
				var cpt = $( '#peiwm-cpt-select' ).val();
				if ( cpt ) {
					loadExportPostsList( cpt, 0, false, $( self ).val() );
				}
			}, 350 );
		} );

		// Export: load more — now handled inline in loadExportPostsList via peiwm-cpt-load-more-btn
		// (old #peiwm-cpt-export-load-more click handler removed)

		// --- Export button ---
		$( '#peiwm-export-cpt' ).on( 'click', function () {
			var cpt = $( '#peiwm-cpt-select' ).val();
			if ( ! cpt ) {
				showError( s.select_post_type );
				return;
			}
			var exportAcf    = $( '#peiwm-cpt-export-acf-fields' ).is( ':checked' ) ? '1' : '0';
			var acfFieldKeys = getSelectedAcfFieldKeys();
			var isSelective  = $( '#peiwm-cpt-export-selective' ).is( ':checked' );
			var selectedIds  = isSelective ? getSelectedIds( $( '#peiwm-cpt-export-posts-list' ) ) : [];

			if ( isBatch && ! isSelective ) {
				startBatchExport( cpt, exportAcf, acfFieldKeys );
			} else {
				startChunkedExport( cpt, exportAcf, selectedIds, acfFieldKeys );
			}
		} );

		// --- Import: file selection (supports multiple JSON files like post import) ---
		$( '#peiwm-cpt-select-import-file' ).on( 'click', function () {
			$( '#peiwm-cpt-import-file' ).trigger( 'click' );
		} );

		$( '#peiwm-cpt-import-file' ).on( 'change', function () {
			var files = this.files;
			if ( ! files || ! files.length ) { return; }

			// Validate all files are JSON
			for ( var fi = 0; fi < files.length; fi++ ) {
				if ( ! files[ fi ].name.toLowerCase().endsWith( '.json' ) ) {
					showError( 'Please select JSON file(s) only.' );
					return;
				}
			}

			var label = files.length > 1 ? files.length + ' files selected' : files[ 0 ].name;
			$( '#peiwm-cpt-select-import-file' ).text( label );
			$( '#peiwm-cpt-start-import' ).show();

			// Load ALL selected files and merge into one list (same as post import)
			loadCptImportFilesIntoList( Array.from( files ) );
		} );

		// Import: toggle selective panel
		$( '#peiwm-cpt-import-selective' ).on( 'change', function () {
			if ( $( this ).is( ':checked' ) ) {
				$( '#peiwm-cpt-import-selective-panel' ).show();
				if ( importedPostsData.length ) {
					renderImportSelectiveList( importedPostsData );
				}
			} else {
				$( '#peiwm-cpt-import-selective-panel' ).hide();
			}
		} );

		// Import: select all
		$( '#peiwm-cpt-import-select-all' ).on( 'change', function () {
			var checked = $( this ).is( ':checked' );
			$( '#peiwm-cpt-import-posts-list .peiwm-selective-checkbox' ).prop( 'checked', checked );
			updateSelectedCount( $( '#peiwm-cpt-import-selected-count' ), $( '#peiwm-cpt-import-posts-list' ) );
		} );

		// Import: search
		$( '#peiwm-cpt-import-search' ).on( 'keyup', function () {
			var q = $( this ).val().toLowerCase();
			$( '#peiwm-cpt-import-posts-list .peiwm-selective-item' ).each( function () {
				var title = $( this ).data( 'title' ) || '';
				$( this ).toggle( title.toLowerCase().indexOf( q ) !== -1 );
			} );
		} );

		// --- Import button ---
		$( '#peiwm-cpt-start-import' ).on( 'click', function () {
			if ( ! importedPostsData.length ) {
				showError( 'Please select a JSON file first.' );
				return;
			}

			var checkMedia    = $( '#peiwm-cpt-check-media-library' ).is( ':checked' ) ? '1' : '0';
			var downloadMedia = $( '#peiwm-cpt-download-missing-images' ).is( ':checked' ) ? '1' : '0';
			var isSelective   = $( '#peiwm-cpt-import-selective' ).is( ':checked' );
			var button        = $( this );

			button.prop( 'disabled', true ).text( 'Importing...' );
			$( '#peiwm-cpt-import-progress' ).show();
			$( 'html, body' ).animate( { scrollTop: $( '#peiwm-cpt-import-progress' ).offset().top - 40 }, 400 );

			// Attach force_status from per-post settings to each post object
			var allFilesData = importedFilesData.map( function ( fileData ) {
				return fileData.map( function ( post, i ) {
					var globalIdx = post._globalIdx !== undefined ? post._globalIdx : i;
					var setting   = window.peiwmCptImportSettings[ globalIdx ] || { force_status: 'original' };
					return Object.assign( {}, post, { _force_status: setting.force_status || 'original' } );
				} );
			} );

			if ( isSelective ) {
				// Filter to only selected indexes
				var selectedIdxs = getSelectedImportIndexes();
				if ( ! selectedIdxs.length ) {
					showError( 'Please select at least one post to import.' );
					button.prop( 'disabled', false ).text( 'Start Import' );
					return;
				}
				// Build a flat filtered array per file
				var globalIdx = 0;
				allFilesData = allFilesData.map( function ( fileData ) {
					return fileData.filter( function () {
						var keep = selectedIdxs.indexOf( globalIdx ) !== -1;
						globalIdx++;
						return keep;
					} );
				} );
			}

			// Filter out empty files
			var filesToProcess = allFilesData.map( function ( data, i ) {
				return { data: data, name: importedFileNames[ i ] || ( 'file' + ( i + 1 ) ) };
			} ).filter( function ( f ) { return f.data.length > 0; } );

			if ( ! filesToProcess.length ) {
				showError( 'No posts to import.' );
				button.prop( 'disabled', false ).text( 'Start Import' );
				return;
			}

			startCptImportFromFiles( filesToProcess, checkMedia, downloadMedia, button );
		} );

	} );

	// =========================================================================
	// ACF FIELD PICKER
	// =========================================================================

	function updateAcfFieldCount() {
		var total   = $( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-checkbox' ).length;
		var checked = $( '#peiwm-cpt-acf-fields-list .peiwm-acf-field-checkbox:checked' ).length;
		var label   = checked === total ? 'All ' + total + ' fields' : checked + ' / ' + total + ' selected';
		$( '#peiwm-cpt-acf-fields-count' ).text( label );
	}

	function loadAcfFieldPicker( cpt ) {
		var $picker = $( '#peiwm-cpt-acf-field-picker' );
		var $list   = $( '#peiwm-cpt-acf-fields-list' );
		var $search = $( '#peiwm-cpt-acf-field-search' );

		$picker.show();

		// Use cache if same CPT
		if ( acfFieldsLoaded === cpt && acfFieldsCache.length ) {
			renderAcfFieldPickerGroups( acfFieldsCache );
			return;
		}

		$search.val( '' );
		$list.html( '<div style="padding:10px 12px;color:#6b7280;font-size:0.85rem;">Loading fields…</div>' );
		$( '#peiwm-cpt-acf-fields-count' ).text( '' );

		$.post( cfg.ajax_url, {
			action:    'peim_get_cpt_acf_fields',
			nonce:     cfg.nonce,
			post_type: cpt,
		}, function ( res ) {
			if ( ! res.success || ! res.data || ! res.data.groups || ! res.data.groups.length ) {
				$list.html( '<div style="padding:10px 12px;color:#9ca3af;font-size:0.85rem;">No ACF fields found for this post type.</div>' );
				$( '#peiwm-cpt-acf-fields-count' ).text( '' );
				return;
			}
			acfFieldsLoaded = cpt;
			acfFieldsCache  = res.data.groups;
			renderAcfFieldPickerGroups( res.data.groups );
		} );
	}

	function renderAcfFieldPickerGroups( groups ) {
		var $list = $( '#peiwm-cpt-acf-fields-list' );
		$list.empty();

		$.each( groups, function ( gi, group ) {
			// Group header
			var $header = $(
				'<div class="peiwm-acf-group-header" style="' +
					'display:flex;align-items:center;gap:6px;' +
					'padding:5px 10px;background:#f1f5f9;' +
					'border-bottom:1px solid #e2e8f0;font-size:0.8rem;font-weight:600;color:#374151;' +
					'position:sticky;top:0;z-index:1;cursor:pointer;" ' +
					'data-group="' + gi + '">' +
					'<span class="peiwm-acf-group-toggle" style="font-size:0.7rem;transition:transform 0.15s;">▼</span>' +
					'<label style="display:flex;align-items:center;gap:6px;cursor:pointer;flex:1;">' +
						'<input type="checkbox" class="peiwm-acf-group-checkbox" data-group="' + gi + '" checked ' +
							'style="margin:0;">' +
						'<span>' + $( '<span>' ).text( group.title ).html() + '</span>' +
						'<span style="font-weight:400;color:#6b7280;">(' + group.fields.length + ' fields)</span>' +
					'</label>' +
				'</div>'
			);

			var $fields = $( '<div class="peiwm-acf-group-fields" data-group="' + gi + '" style="padding:2px 0;"></div>' );

			$.each( group.fields, function ( fi, field ) {
				var typeColor = {
					text: '#2563eb', textarea: '#2563eb', number: '#7c3aed',
					image: '#059669', file: '#059669', gallery: '#059669',
					select: '#d97706', checkbox: '#d97706', radio: '#d97706', true_false: '#d97706',
					relationship: '#dc2626', post_object: '#dc2626', taxonomy: '#0891b2',
					repeater: '#7c3aed', group: '#7c3aed', flexible_content: '#7c3aed',
				}[ field.type ] || '#6b7280';

				var $item = $(
					'<label class="peiwm-acf-field-item" ' +
						'data-label="' + $( '<span>' ).text( field.label ).html() + '" ' +
						'data-name="' + $( '<span>' ).text( field.name ).html() + '" ' +
						'style="display:flex;align-items:center;gap:8px;padding:5px 12px 5px 24px;' +
							'cursor:pointer;border-bottom:1px solid #f1f5f9;' +
							'transition:background 0.1s;" ' +
						'onmouseover="this.style.background=\'#f8fafc\'" ' +
						'onmouseout="this.style.background=\'\'">' +
						'<input type="checkbox" class="peiwm-acf-field-checkbox" ' +
							'value="' + $( '<span>' ).text( field.key ).html() + '" checked ' +
							'style="margin:0;flex-shrink:0;">' +
						'<span style="flex:1;min-width:0;">' +
							'<span style="font-size:0.85rem;color:#111827;font-weight:500;">' +
								$( '<span>' ).text( field.label ).html() +
							'</span>' +
							'<span style="font-size:0.75rem;color:#9ca3af;margin-left:4px;">' +
								field.name +
							'</span>' +
						'</span>' +
						'<span style="font-size:0.7rem;padding:1px 6px;border-radius:10px;' +
							'background:#f1f5f9;color:' + typeColor + ';font-weight:500;flex-shrink:0;">' +
							field.type +
						'</span>' +
					'</label>'
				);

				$item.find( '.peiwm-acf-field-checkbox' ).on( 'change', function () {
					updateGroupCheckboxState( gi );
					updateAcfFieldCount();
				} );

				$fields.append( $item );
			} );

			// Group checkbox toggles all children
			$header.find( '.peiwm-acf-group-checkbox' ).on( 'change', function ( e ) {
				e.stopPropagation();
				var checked = $( this ).is( ':checked' );
				$fields.find( '.peiwm-acf-field-checkbox' ).prop( 'checked', checked );
				updateAcfFieldCount();
			} );

			// Click on header row (not checkbox) collapses group
			$header.on( 'click', function ( e ) {
				if ( $( e.target ).is( 'input' ) ) { return; }
				var $toggle = $header.find( '.peiwm-acf-group-toggle' );
				if ( $fields.is( ':visible' ) ) {
					$fields.slideUp( 150 );
					$toggle.css( 'transform', 'rotate(-90deg)' );
				} else {
					$fields.slideDown( 150 );
					$toggle.css( 'transform', '' );
				}
			} );

			$list.append( $header ).append( $fields );
		} );

		updateAcfFieldCount();

		// Delegate: field checkbox change also updates count
		$list.off( 'change.acf-count' ).on( 'change.acf-count', '.peiwm-acf-field-checkbox', function () {
			updateAcfFieldCount();
		} );
	}

	function updateGroupCheckboxState( gi ) {
		var $fields   = $( '#peiwm-cpt-acf-fields-list .peiwm-acf-group-fields[data-group="' + gi + '"]' );
		var total     = $fields.find( '.peiwm-acf-field-checkbox' ).length;
		var checked   = $fields.find( '.peiwm-acf-field-checkbox:checked' ).length;
		var $groupChk = $( '#peiwm-cpt-acf-fields-list .peiwm-acf-group-checkbox[data-group="' + gi + '"]' );
		if ( checked === 0 ) {
			$groupChk.prop( { checked: false, indeterminate: false } );
		} else if ( checked === total ) {
			$groupChk.prop( { checked: true, indeterminate: false } );
		} else {
			$groupChk.prop( { checked: false, indeterminate: true } );
		}
	}

	// =========================================================================
	// LOAD CPT LIST
	// =========================================================================

	function loadCptList() {
		if ( cfg.is_pro_active !== '1' ) {
			return;
		}

		$.post( cfg.ajax_url, {
			action: 'peim_get_cpt_list',
			nonce:  cfg.nonce,
		}, function ( res ) {
			if ( ! res.success ) {
				$( '#peiwm-cpt-select' ).html( '<option value="">No post types found</option>' );
				return;
			}
			var opts = '<option value="">— Select post type —</option>';
			$.each( res.data, function ( i, cpt ) {
				opts += '<option value="' + cpt.name + '">' + cpt.label + ' (' + cpt.count + ')</option>';
			} );
			$( '#peiwm-cpt-select' ).html( opts );

			// Render "Export All CPT Types" button once we know there are CPTs
			if ( res.data.length > 1 ) {
				var $exportRow = $( '#peiwm-export-cpt' ).closest( '.peiwm-form-row, p, div' );
				if ( ! $( '#peiwm-export-all-cpts' ).length ) {
					var totalLabel = res.data.length + ' post type' + ( res.data.length === 1 ? '' : 's' );
					$( '#peiwm-export-cpt' ).after(
						'<button type="button" id="peiwm-export-all-cpts" class="button button-secondary" style="margin-left:0.5rem;" title="Export every CPT to a separate JSON file">' +
						'⬇ Export All (' + totalLabel + ')' +
						'</button>'
					);
				}
			}
		} );
	}

	// =========================================================================
	// EXPORT: ALL CPT TYPES (one JSON file per CPT, sequential)
	// =========================================================================

	/**
	 * Fetch and download all non-built-in CPTs one by one.
	 * Each CPT becomes one JSON download. Uses the same peim_export_all_cpts
	 * AJAX endpoint (which mirrors ajax_export_cpt but is dedicated so the
	 * single-CPT export flow is unaffected).
	 */
	function startExportAllCpts( exportAcf ) {
		if ( cfg.is_pro_active !== '1' ) { return; }

		var $progress = $( '#peiwm-cpt-export-progress' );
		var $btn      = $( '#peiwm-export-all-cpts' );
		var $btnSingle = $( '#peiwm-export-cpt' );

		$progress.show();
		$btn.prop( 'disabled', true ).text( 'Exporting all…' );
		$btnSingle.prop( 'disabled', true );
		$progress.find( '.peiwm-log' ).empty();
		updateProgress( $progress, 0, 'Loading CPT list…' );

		// First fetch the fresh CPT list
		$.post( cfg.ajax_url, {
			action: 'peim_get_cpt_list',
			nonce:  cfg.nonce,
		}, function ( res ) {
			if ( ! res.success || ! res.data.length ) {
				appendLog( $progress, '✗ No post types found.', true );
				$btn.prop( 'disabled', false ).text( '⬇ Export All' );
				$btnSingle.prop( 'disabled', false );
				return;
			}

			var cptList      = res.data; // [{name, label, count}]
			var totalCpts    = cptList.length;
			var currentCpt   = 0;
			var totalExported = 0;

			appendLog( $progress, '📋 Found ' + totalCpts + ' post type(s). Starting export…' );

			function exportNextCpt() {
				if ( currentCpt >= totalCpts ) {
					// All done
					updateProgress( $progress, 100, 'All CPTs exported! (' + totalExported + ' posts total)' );
					appendLog( $progress, '✅ Export complete! ' + totalExported + ' posts across ' + totalCpts + ' post type(s).' );
					showSuccess( 'All CPTs exported! ' + totalExported + ' posts in ' + totalCpts + ' files.' );
					$btn.prop( 'disabled', false ).text( '⬇ Export All (' + totalCpts + ' post types)' );
					$btnSingle.prop( 'disabled', false );
					return;
				}

				var cpt         = cptList[ currentCpt ];
				var cptName     = cpt.name;
				var cptLabel    = cpt.label;
				var cptCount    = cpt.count || 0;
				var page        = 1;
				var perPage     = 50;
				var cptPosts    = [];

				$btn.text( 'Exporting ' + cptLabel + '… (' + ( currentCpt + 1 ) + '/' + totalCpts + ')' );
				appendLog( $progress, '📁 Exporting: ' + cptLabel + ' (' + cptCount + ' posts)' );

				function fetchCptChunk() {
					$.ajax( {
						url:  cfg.ajax_url,
						type: 'POST',
						data: {
							action:            'peim_export_all_cpts',
							nonce:             cfg.nonce,
							post_type:         cptName,
							export_acf_fields: exportAcf,
							page:              page,
							per_page:          perPage,
						},
						success: function ( res2 ) {
							if ( ! res2.success ) {
								appendLog( $progress, '  ✗ Error exporting ' + cptLabel + ': ' + ( res2.data && res2.data.message ? res2.data.message : 'unknown' ), true );
								currentCpt++;
								exportNextCpt();
								return;
							}

							cptPosts = cptPosts.concat( res2.data.posts );

							var fetched  = res2.data.posts.length;
							var total    = res2.data.total || cptCount;
							var pctCpt   = total > 0 ? Math.round( ( cptPosts.length / total ) * 100 ) : 100;
							var pctTotal = Math.round( ( ( currentCpt + pctCpt / 100 ) / totalCpts ) * 100 );
							updateProgress( $progress, pctTotal,
								'Exporting ' + cptLabel + ': ' + cptPosts.length + ' / ' + total +
								' — CPT ' + ( currentCpt + 1 ) + ' of ' + totalCpts
							);

							if ( res2.data.has_more ) {
								page++;
								setTimeout( fetchCptChunk, 150 );
							} else {
								// Done with this CPT — trigger download
								if ( cptPosts.length > 0 ) {
									var filename = 'cpt-' + cptName + '-export_' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
									triggerJsonDownload( JSON.stringify( cptPosts, null, 2 ), filename );
									appendLog( $progress, '  ✓ Downloaded: ' + filename + ' (' + cptPosts.length + ' posts)' );
									totalExported += cptPosts.length;
								} else {
									appendLog( $progress, '  ⚠ ' + cptLabel + ': no posts found, skipped.' );
								}
								currentCpt++;
								setTimeout( exportNextCpt, 400 );
							}
						},
						error: function ( xhr, status, error ) {
							appendLog( $progress, '  ✗ Network error for ' + cptLabel + ': ' + error, true );
							currentCpt++;
							setTimeout( exportNextCpt, 400 );
						},
					} );
				}

				fetchCptChunk();
			}

			exportNextCpt();
		} );
	}

	// =========================================================================
	// EXPORT: LOAD POSTS LIST (for selective panel)
	// =========================================================================

	// Track pagination state per CPT load
	var exportListState = {
		offset:    0,
		pageSize:  300,
		totalCount: 0,
		hasMore:   false,
	};

	function loadExportPostsList( cpt, offset, append, search ) {
		if ( ! append ) {
			$( '#peiwm-cpt-export-posts-list' ).html(
				'<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div><p>Loading...</p></div>'
			);
			$( '#peiwm-cpt-export-load-more-wrap' ).empty();
			$( '.peiwm-cpt-batch-warn' ).remove();
			exportListState.offset = 0;
		}

		$.post( cfg.ajax_url, {
			action:    'peim_get_cpt_posts_list',
			nonce:     cfg.nonce,
			post_type: cpt,
			offset:    offset || 0,
			search:    search || '',
		}, function ( res ) {
			if ( ! res.success ) { return; }

			var data  = res.data;
			var $list = $( '#peiwm-cpt-export-posts-list' );

			// Show batch warning once (same as post handler)
			if ( data.show_batch_warn && ( ! offset || offset === 0 ) ) {
				var $warn = $(
					'<div class="peiwm-cpt-batch-warn" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem;font-size:0.875rem;">' +
					'⚠️ This CPT has <strong>' + data.total_count + ' posts</strong>. Enable <strong><a href="?page=peiwm-batch-settings">Batch Processing</a></strong> for better performance on large sites.' +
					'</div>'
				);
				$( '#peiwm-cpt-export-selective-panel' ).prepend( $warn );
			}

			if ( ! append ) { $list.empty(); }

			if ( ! data.posts.length ) {
				if ( ! append ) { $list.html( '<p class="peiwm-selective-empty">No posts found.</p>' ); }
				$( '#peiwm-cpt-export-load-more-wrap' ).empty();
				return;
			}

			$.each( data.posts, function ( i, post ) {
				var escapedTitle   = $( '<div>' ).text( post.post_title ).html();
				var originalStatus = post.post_status || 'publish';
				var $item = $(
					'<div class="peiwm-selective-item" data-id="' + post.ID + '" data-title="' + escapedTitle + '">' +
						'<label style="display:flex;align-items:center;gap:0.5rem;flex:1;cursor:pointer;min-width:0;">' +
							'<input type="checkbox" class="peiwm-selective-checkbox" value="' + post.ID + '" checked>' +
							'<span class="peiwm-selective-info">' +
								'<span class="peiwm-selective-title">' + escapedTitle + '</span>' +
								'<span class="peiwm-selective-meta">' + post.post_date.slice( 0, 10 ) + '</span>' +
							'</span>' +
						'</label>' +
						'<span class="peiwm-selective-status-wrap">' +
							'<span class="peiwm-selective-status peiwm-status-' + originalStatus + '">' + originalStatus + '</span>' +
						'</span>' +
					'</div>'
				);
				$item.find( '.peiwm-selective-checkbox' ).on( 'change', function () {
					updateSelectedCount( $( '#peiwm-cpt-export-selected-count' ), $list );
					var allChecked = $list.find( '.peiwm-selective-checkbox:not(:checked)' ).length === 0;
					$( '#peiwm-cpt-export-select-all' ).prop( 'checked', allChecked );
				} );
				$list.append( $item );
			} );

			// Update state
			exportListState.offset     = ( offset || 0 ) + data.count;
			exportListState.pageSize   = data.page_size;
			exportListState.totalCount = data.total_count;
			exportListState.hasMore    = data.has_more;

			// Update select-all state
			$( '#peiwm-cpt-export-select-all' ).prop( 'checked', true );
			updateSelectedCount( $( '#peiwm-cpt-export-selected-count' ), $list );

			// Load-more button (same pattern as post handler)
			var $loadMoreWrap = $( '#peiwm-cpt-export-load-more-wrap' );
			if ( data.has_more ) {
				var nextOffset = exportListState.offset;
				var remaining  = data.total_count - nextOffset;
				$loadMoreWrap.html(
					'<button type="button" class="button button-secondary peiwm-cpt-load-more-btn" style="margin-left:0.5rem;font-size:0.8rem;padding:2px 10px;">' +
					'⬇ Load next ' + data.page_size + ' (' + remaining + ' more)' +
					'</button>'
				);
				$loadMoreWrap.find( '.peiwm-cpt-load-more-btn' ).on( 'click', function () {
					$loadMoreWrap.html( '<span style="font-size:0.8rem;color:#6b7280;margin-left:0.5rem;">Loading...</span>' );
					loadExportPostsList( cpt, nextOffset, true, search );
				} );
			} else {
				$loadMoreWrap.html(
					'<span style="font-size:0.8rem;color:#10b981;margin-left:0.5rem;">✓ All ' + data.total_count + ' loaded</span>'
				);
			}
		} );
	}

	// =========================================================================
	// EXPORT: CHUNKED (non-batch) — mirrors admin.js export with file splitting
	// =========================================================================

	function startChunkedExport( cpt, exportAcf, selectedIds, acfFieldKeys ) {
		var $progress  = $( '#peiwm-cpt-export-progress' );
		var $btn       = $( '#peiwm-export-cpt' );
		var isSelective = selectedIds.length > 0;
		acfFieldKeys    = acfFieldKeys || '';

		// postsPerFile: selective = all in one file; non-selective = export_json_size (default 500)
		var postsPerFile = isSelective
			? selectedIds.length
			: Math.max( cfg.export_json_size ? parseInt( cfg.export_json_size, 10 ) : 500, 500 );

		var ajaxChunkSize    = 50;
		var page             = 1;
		var perPage          = ajaxChunkSize;
		var selectiveOffset  = 0;
		var globalOffset     = 0;
		var fileNum          = 0;
		var totalExported    = 0;
		var currentFilePosts = [];

		$progress.show();
		$btn.prop( 'disabled', true ).text( 'Exporting...' );
		$progress.find( '.peiwm-log' ).empty();

		if ( acfFieldKeys ) {
			var keyCount = acfFieldKeys.split( ',' ).length;
			appendLog( $progress, '🔍 Exporting ' + keyCount + ' selected ACF field(s)' );
		}

		function downloadFile( posts ) {
			fileNum++;
			totalExported += posts.length;
			var suffix   = fileNum > 1 ? '_part' + fileNum : '';
			var filename = 'cpt-' + cpt + '-export' + suffix + '_' + new Date().toISOString().slice(0, 19).replace(/T|:/g, '-') + '.json';
			triggerJsonDownload( JSON.stringify( posts, null, 2 ), filename );
			appendLog( $progress, '📥 Downloaded: ' + filename + ' (' + posts.length + ' posts)' );
		}

		function exportNextFile() {
			currentFilePosts = [];

			function fetchChunk() {
				var needed  = postsPerFile - currentFilePosts.length;
				var payload = {
					action:            'peim_export_cpt',
					nonce:             cfg.nonce,
					post_type:         cpt,
					export_acf_fields: exportAcf,
					acf_field_keys:    acfFieldKeys,
					page:              page,
					per_page:          Math.min( needed, ajaxChunkSize ),
				};

				if ( isSelective ) {
					var chunkIds = selectedIds.slice( selectiveOffset, selectiveOffset + Math.min( needed, ajaxChunkSize ) );
					if ( ! chunkIds.length ) {
						if ( currentFilePosts.length ) { downloadFile( currentFilePosts ); }
						var msg = fileNum > 1
							? 'Export complete! ' + totalExported + ' posts in ' + fileNum + ' files.'
							: 'CPT exported! (' + totalExported + ' posts)';
						showSuccess( msg );
						$btn.prop( 'disabled', false ).text( 'Export CPT Posts' );
						updateProgress( $progress, 100, 'Export complete! (' + totalExported + ' posts)' );
						return;
					}
					payload.post_ids = chunkIds.join( ',' );
					payload.page     = 1;
					payload.per_page = chunkIds.length;
				} else {
					payload.page    = page;
					payload.per_page = Math.min( needed, ajaxChunkSize );
				}

				$btn.text( 'Exporting file ' + ( fileNum + 1 ) + '... (' + ( totalExported + currentFilePosts.length ) + ' done)' );

				$.ajax( {
					url:  cfg.ajax_url,
					type: 'POST',
					data: payload,
					success: function ( res ) {
						if ( ! res.success ) {
							appendLog( $progress, '✗ Export failed: ' + ( res.data && res.data.message ? res.data.message : 'Unknown error' ), true );
							$btn.prop( 'disabled', false ).text( 'Export CPT Posts' );
							return;
						}

						currentFilePosts = currentFilePosts.concat( res.data.posts );
						var fetched      = res.data.posts.length;

						if ( isSelective ) {
							selectiveOffset += fetched;
						} else {
							page++;
						}

						var moreExist = isSelective
							? selectiveOffset < selectedIds.length
							: res.data.has_more;

						var total = res.data.total || ( totalExported + currentFilePosts.length );
						var pct   = total ? Math.round( ( ( totalExported + currentFilePosts.length ) / total ) * 100 ) : 50;
						updateProgress( $progress, pct, 'Exporting... ' + ( totalExported + currentFilePosts.length ) + ' / ' + total );

						if ( currentFilePosts.length >= postsPerFile || ! moreExist ) {
							downloadFile( currentFilePosts );
							if ( moreExist ) {
								setTimeout( exportNextFile, 500 );
							} else {
								var doneMsg = fileNum > 1
									? 'Export complete! ' + totalExported + ' posts in ' + fileNum + ' files.'
									: 'CPT exported! (' + totalExported + ' posts)';
								showSuccess( doneMsg );
								$btn.prop( 'disabled', false ).text( 'Export CPT Posts' );
								updateProgress( $progress, 100, 'Export complete! (' + totalExported + ' posts)' );
							}
						} else {
							setTimeout( fetchChunk, 100 );
						}
					},
					error: function ( xhr, status, error ) {
						appendLog( $progress, '✗ Export error: ' + error, true );
						$btn.prop( 'disabled', false ).text( 'Export CPT Posts' );
					},
				} );
			}

			fetchChunk();
		}

		exportNextFile();
	}

	// =========================================================================
	// EXPORT: BATCH (via PHP batch session)
	// =========================================================================

	function startBatchExport( cpt, exportAcf, acfFieldKeys ) {
		var $progress = $( '#peiwm-cpt-export-progress' );
		$progress.show();
		$( '#peiwm-export-cpt' ).prop( 'disabled', true );
		$progress.find( '.peiwm-log' ).empty();
		acfFieldKeys = acfFieldKeys || '';

		updateProgress( $progress, 0, s.processing );

		if ( acfFieldKeys ) {
			var keyCount = acfFieldKeys.split( ',' ).length;
			appendLog( $progress, '🔍 Exporting ' + keyCount + ' selected ACF field(s)' );
		}

		$.post( cfg.ajax_url, {
			action:            'peim_batch_export_cpt_start',
			nonce:             cfg.nonce,
			post_type:         cpt,
			export_acf_fields: exportAcf,
			acf_field_keys:    acfFieldKeys,
		}, function ( res ) {
			if ( ! res.success ) {
				appendLog( $progress, s.error + ' ' + ( res.data && res.data.message ? res.data.message : '' ), true );
				$( '#peiwm-export-cpt' ).prop( 'disabled', false );
				return;
			}
			processBatchExportChunk( res.data.batch_id, 1, res.data.total_batches, res.data.total, $progress );
		} );
	}

	function processBatchExportChunk( batchId, batchNum, totalBatches, total, $progress ) {
		$.post( cfg.ajax_url, {
			action:       'peim_batch_export_cpt_process',
			nonce:        cfg.nonce,
			batch_id:     batchId,
			batch_number: batchNum,
		}, function ( res ) {
			if ( ! res.success ) {
				appendLog( $progress, s.error + ' ' + ( res.data && res.data.message ? res.data.message : '' ), true );
				$( '#peiwm-export-cpt' ).prop( 'disabled', false );
				return;
			}
			var pct = totalBatches ? Math.round( ( batchNum / totalBatches ) * 100 ) : 100;
			updateProgress( $progress, pct, s.exporting + ' ' + res.data.processed + ' / ' + total );
			appendLog( $progress, 'Batch ' + batchNum + ' processed (' + res.data.processed + ' posts)' );

			if ( res.data.has_more ) {
				processBatchExportChunk( batchId, batchNum + 1, totalBatches, total, $progress );
			} else {
				// Done — server wrote the file, redirect to download URL
				updateProgress( $progress, 100, s.export_complete );
				appendLog( $progress, s.export_complete );
				if ( res.data.download_url ) {
					var $dlLink = $( '<a href="' + res.data.download_url + '" class="button button-secondary" style="margin-top:0.5rem;">Download Export File</a>' );
					$progress.find( '.peiwm-log' ).append( $dlLink );
				}
				$( '#peiwm-export-cpt' ).prop( 'disabled', false );
			}
		} );
	}

	// =========================================================================
	// IMPORT: LOAD MULTIPLE FILES INTO SELECTIVE LIST
	// =========================================================================

	/**
	 * Read all selected files, merge posts, populate importedFilesData/importedPostsData,
	 * and render the selective list. Mirrors loadPostsSelectionListFromFiles() in admin.js.
	 */
	function loadCptImportFilesIntoList( files ) {
		$( '#peiwm-cpt-import-posts-list' ).html(
			'<div class="peiwm-selective-loading"><div class="peiwm-loading-spinner"></div>' +
			'<p>Loading posts from ' + files.length + ' file(s)...</p></div>'
		);

		importedFilesData = [];
		importedFileNames = [];
		importedPostsData = [];
		window.peiwmCptImportSettings = {};

		var loaded  = 0;
		var errors  = 0;
		var allData = []; // indexed by fileIdx

		files.forEach( function ( file, fileIdx ) {
			importedFileNames[ fileIdx ] = file.name;
			var reader = new FileReader();
			reader.onload = function ( e ) {
				try {
					var parsed = JSON.parse( e.target.result );
					if ( ! Array.isArray( parsed ) ) { throw new Error( 'Not an array' ); }
					allData[ fileIdx ] = parsed;
				} catch ( err ) {
					errors++;
					allData[ fileIdx ] = [];
				}
				loaded++;
				if ( loaded === files.length ) {
					// Merge all files into flat list, tagging each post with _globalIdx and _fileIdx
					var globalIdx = 0;
					allData.forEach( function ( fileData, fi ) {
						importedFilesData[ fi ] = fileData;
						fileData.forEach( function ( post ) {
							post._globalIdx = globalIdx;
							post._fileIdx   = fi;
							importedPostsData.push( post );
							globalIdx++;
						} );
					} );

					if ( errors > 0 && importedPostsData.length === 0 ) {
						$( '#peiwm-cpt-import-posts-list' ).html( '<p class="peiwm-selective-empty">Invalid JSON file(s).</p>' );
					} else {
						if ( errors > 0 ) {
							$( '#peiwm-cpt-import-posts-list' ).before(
								'<p style="color:#d97706;font-size:0.85rem;margin-bottom:0.5rem;">⚠ ' + errors + ' file(s) could not be read.</p>'
							);
						}
						// Auto-render if selective panel is open
						if ( $( '#peiwm-cpt-import-selective' ).is( ':checked' ) ) {
							renderImportSelectiveList( importedPostsData );
						} else {
							$( '#peiwm-cpt-import-posts-list' ).html(
								'<p class="peiwm-selective-empty">👆 Enable "Import individually" to select specific posts.</p>'
							);
						}
					}
				}
			};
			reader.readAsText( file );
		} );
	}

	// =========================================================================
	// IMPORT: RENDER SELECTIVE LIST
	// =========================================================================

	// Per-post import status overrides: { globalIdx: { force_status: 'publish'|'draft'|'private'|'original' } }
	window.peiwmCptImportSettings = {};

	function renderImportSelectiveList( posts ) {
		var $list = $( '#peiwm-cpt-import-posts-list' );
		$list.empty();

		$.each( posts, function ( listIdx, post ) {
			var title          = post.post_title || '(no title)';
			var originalStatus = post.post_status || 'publish';
			var escapedTitle   = $( '<div>' ).text( title ).html();
			var globalIdx      = post._globalIdx !== undefined ? post._globalIdx : listIdx;
			var date           = post.post_date ? post.post_date.slice( 0, 10 ) : '';

			var $item = $(
				'<div class="peiwm-selective-item" data-index="' + globalIdx + '" data-title="' + escapedTitle + '">' +
					'<label style="display:flex;align-items:center;gap:0.5rem;flex:1;cursor:pointer;min-width:0;">' +
						'<input type="checkbox" class="peiwm-selective-checkbox" value="' + globalIdx + '" checked>' +
						'<span class="peiwm-selective-info">' +
							'<span class="peiwm-selective-title">' + escapedTitle + '</span>' +
							'<span class="peiwm-selective-meta">' + date + '</span>' +
						'</span>' +
					'</label>' +
					'<span class="peiwm-selective-status-wrap">' +
						'<span class="peiwm-selective-status peiwm-status-' + originalStatus + '" data-index="' + globalIdx + '">' + originalStatus + '</span>' +
						'<button type="button" class="peiwm-item-settings-btn" data-index="' + globalIdx + '" title="Change import status">&#9881;</button>' +
					'</span>' +
				'</div>'
			);

			$item.find( '.peiwm-selective-checkbox' ).on( 'change', function () {
				updateSelectedCount( $( '#peiwm-cpt-import-selected-count' ), $list );
				var allChecked = $list.find( '.peiwm-selective-checkbox:not(:checked)' ).length === 0;
				$( '#peiwm-cpt-import-select-all' ).prop( 'checked', allChecked );
			} );

			$item.find( '.peiwm-item-settings-btn' ).on( 'click', function () {
				openCptImportSettingsModal( globalIdx, post );
			} );

			$list.append( $item );
		} );

		$( '#peiwm-cpt-import-select-all' ).prop( 'checked', true );
		updateSelectedCount( $( '#peiwm-cpt-import-selected-count' ), $list );
	}

	/**
	 * Open the per-post status settings modal (same UX as post import ⚙ modal).
	 */
	function openCptImportSettingsModal( index, post ) {
		var settings       = window.peiwmCptImportSettings[ index ] || { force_status: 'original' };
		var title          = post.post_title || '(no title)';
		var originalStatus = post.post_status || 'publish';
		var escapedTitle   = $( '<div>' ).text( title ).html();

		var body =
			'<div style="text-align:left;">' +
				'<p style="margin-bottom:1rem;color:#4a5568;">' +
					'<strong>' + escapedTitle + '</strong><br>' +
					'<small>Original status: <span class="peiwm-selective-status peiwm-status-' + originalStatus + '">' + originalStatus + '</span></small>' +
				'</p>' +
				'<label style="display:block;margin-bottom:0.5rem;font-weight:600;">Import as status:</label>' +
				'<div class="peiwm-status-options">' +
					'<label class="peiwm-status-option">' +
						'<input type="radio" name="peiwm_cpt_force_status" value="original" ' + ( settings.force_status === 'original' ? 'checked' : '' ) + '>' +
						'<span>Keep original <span class="peiwm-selective-status peiwm-status-' + originalStatus + '">' + originalStatus + '</span></span>' +
					'</label>' +
					'<label class="peiwm-status-option">' +
						'<input type="radio" name="peiwm_cpt_force_status" value="publish" ' + ( settings.force_status === 'publish' ? 'checked' : '' ) + '>' +
						'<span><span class="peiwm-selective-status peiwm-status-publish">publish</span></span>' +
					'</label>' +
					'<label class="peiwm-status-option">' +
						'<input type="radio" name="peiwm_cpt_force_status" value="draft" ' + ( settings.force_status === 'draft' ? 'checked' : '' ) + '>' +
						'<span><span class="peiwm-selective-status peiwm-status-draft">draft</span></span>' +
					'</label>' +
					'<label class="peiwm-status-option">' +
						'<input type="radio" name="peiwm_cpt_force_status" value="private" ' + ( settings.force_status === 'private' ? 'checked' : '' ) + '>' +
						'<span><span class="peiwm-selective-status peiwm-status-private">private</span></span>' +
					'</label>' +
				'</div>' +
				'<p style="margin-top:1rem;font-size:0.8rem;color:#718096;">' +
					'If this post already exists and the status differs, it will be updated to the selected status.' +
				'</p>' +
			'</div>';

		var $modal = $( '#peiwm-modal-overlay' );
		$modal.find( '.peiwm-modal-header h3' ).text( 'Import Settings' );
		$modal.find( '.peiwm-modal-body p' ).html( body );
		$modal.find( '.peiwm-modal' ).removeClass( 'peiwm-warning-modal peiwm-danger-modal' );
		$modal.show().addClass( 'peiwm-show' );

		var $confirmBtn = $modal.find( '#peiwm-modal-confirm' );
		var $cancelBtn  = $modal.find( '#peiwm-modal-cancel' );
		$confirmBtn.off( 'click' ).text( 'Apply' );
		$cancelBtn.off( 'click' ).text( 'Cancel' );

		function closeModal() {
			$modal.removeClass( 'peiwm-show' ).hide();
			$( document ).off( 'keydown.peiwm-cpt-modal' );
		}

		$confirmBtn.on( 'click', function () {
			var selected = $modal.find( 'input[name="peiwm_cpt_force_status"]:checked' ).val() || 'original';
			window.peiwmCptImportSettings[ index ] = { force_status: selected };

			// Update the status badge in the list
			var displayStatus = selected === 'original' ? originalStatus : selected;
			$( '#peiwm-cpt-import-posts-list .peiwm-selective-item[data-index="' + index + '"] .peiwm-selective-status' )
				.attr( 'class', 'peiwm-selective-status peiwm-status-' + displayStatus )
				.text( displayStatus );

			closeModal();
		} );

		$cancelBtn.on( 'click', closeModal );
		$modal.find( '.peiwm-modal-close' ).off( 'click' ).on( 'click', closeModal );
		$modal.off( 'click.cpt-settings' ).on( 'click.cpt-settings', function ( e ) {
			if ( e.target === this ) { closeModal(); }
		} );
		$( document ).off( 'keydown.peiwm-cpt-modal' ).on( 'keydown.peiwm-cpt-modal', function ( e ) {
			if ( e.key === 'Escape' ) { closeModal(); }
		} );
	}

	function getSelectedImportIndexes() {
		var idxs = [];
		$( '#peiwm-cpt-import-posts-list .peiwm-selective-checkbox:checked' ).each( function () {
			idxs.push( parseInt( $( this ).val(), 10 ) );
		} );
		return idxs;
	}

	// =========================================================================
	// IMPORT: MULTI-FILE PROCESSOR WITH FILE TRACKER (mirrors startImportFromAllFiles)
	// =========================================================================

	function startCptImportFromFiles( filesToProcess, checkMedia, downloadMedia, button ) {
		var totalFilesToProcess = filesToProcess.length;
		var currentFileIndex    = 0;
		var $progress           = $( '#peiwm-cpt-import-progress' );

		// Build file tracker UI (same as admin.js)
		var trackerHtml = '<div id="peiwm-cpt-file-tracker" style="margin-bottom:0.75rem;padding:0.6rem 0.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:0.82rem;">';
		trackerHtml += '<div style="font-weight:600;margin-bottom:0.4rem;color:#374151;">📂 Files (' + totalFilesToProcess + ' total)</div>';
		trackerHtml += '<div id="peiwm-cpt-file-tracker-list">';
		filesToProcess.forEach( function ( f, i ) {
			trackerHtml += '<div id="peiwm-cpt-file-row-' + i + '" style="display:flex;align-items:center;gap:0.4rem;padding:2px 0;">' +
				'<span id="peiwm-cpt-file-icon-' + i + '" style="width:1.1rem;text-align:center;">⏳</span>' +
				'<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + f.name + '">' + f.name + '</span>' +
				'<span id="peiwm-cpt-file-status-' + i + '" style="color:#6b7280;font-size:0.78rem;">' + f.data.length + ' posts · pending</span>' +
			'</div>';
		} );
		trackerHtml += '</div></div>';

		$progress.find( '#peiwm-cpt-file-tracker' ).remove();
		$progress.find( '.peiwm-progress-bar' ).before( trackerHtml );
		$progress.find( '.peiwm-log' ).empty();

		function markRunning( i ) {
			$( '#peiwm-cpt-file-icon-' + i ).text( '▶' );
			$( '#peiwm-cpt-file-status-' + i ).text( filesToProcess[ i ].data.length + ' posts · running…' ).css( 'color', '#2563eb' );
		}
		function markDone( i ) {
			$( '#peiwm-cpt-file-icon-' + i ).text( '✅' );
			$( '#peiwm-cpt-file-status-' + i ).text( filesToProcess[ i ].data.length + ' posts · done' ).css( 'color', '#16a34a' );
		}
		function markPartial( i, failedCount ) {
			$( '#peiwm-cpt-file-icon-' + i ).text( '⚠️' );
			$( '#peiwm-cpt-file-status-' + i ).text( filesToProcess[ i ].data.length + ' posts · done (' + failedCount + ' failed)' ).css( 'color', '#d97706' );
		}

		function processNextFile() {
			if ( currentFileIndex >= totalFilesToProcess ) {
				button.prop( 'disabled', false ).text( 'Start Import' );
				if ( totalFilesToProcess > 1 ) {
					showSuccess( 'All ' + totalFilesToProcess + ' files imported successfully!' );
				}
				return;
			}

			var fileInfo     = filesToProcess[ currentFileIndex ];
			var capturedIdx  = currentFileIndex;
			markRunning( capturedIdx );

			if ( totalFilesToProcess > 1 ) {
				button.text( 'Importing file ' + ( currentFileIndex + 1 ) + ' of ' + totalFilesToProcess + '...' );
			}

			var onComplete = function ( failedCount ) {
				if ( failedCount && failedCount > 0 ) {
					markPartial( capturedIdx, failedCount );
				} else {
					markDone( capturedIdx );
				}
				currentFileIndex++;
				processNextFile();
			};

			if ( isBatch ) {
				cptBatchImportPosts( fileInfo.data, fileInfo.name, capturedIdx + 1, totalFilesToProcess, checkMedia, downloadMedia, onComplete );
			} else {
				cptSequentialImportPosts( fileInfo.data, fileInfo.name, capturedIdx + 1, totalFilesToProcess, checkMedia, downloadMedia, onComplete );
			}
		}

		processNextFile();
	}

	// =========================================================================
	// IMPORT: SEQUENTIAL (regular mode — mirrors importPosts in admin.js)
	// =========================================================================

	function cptSequentialImportPosts( posts, fileLabel, fileIndex, totalFiles, checkMedia, downloadMedia, onComplete ) {
		var $progress    = $( '#peiwm-cpt-import-progress' );
		var $fill        = $progress.find( '.peiwm-progress-fill' );
		var $text        = $progress.find( '.peiwm-progress-text' );
		var $log         = $progress.find( '.peiwm-log' );
		var totalPosts   = posts.length;
		var currentIndex = 0;
		var isProcessing = false;
		var failedPosts  = [];

		$progress.show();
		$fill.css( 'width', '0%' );
		$text.text( 'Starting import…' );
		$log.empty();

		function processNextPost() {
			if ( currentIndex >= totalPosts ) {
				$text.text( 'Import complete!' );
				if ( failedPosts.length > 0 ) {
					var failedCount = failedPosts.length;
					appendLog( $progress, '⚠ ' + failedCount + ' post(s) failed.', true );
					var $retryBtn = $( '<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">🔄 Retry ' + failedCount + ' failed post(s)</button>' );
					$log.after( $retryBtn );
					$retryBtn.on( 'click', function () {
						$retryBtn.remove();
						var retryData = failedPosts.splice( 0 );
						cptSequentialImportPosts( retryData, fileLabel, fileIndex, totalFiles, checkMedia, downloadMedia, onComplete );
					} );
					showSuccess( 'Import done! ' + totalPosts + ' processed. ' + failedCount + ' need retry.' );
					if ( typeof onComplete === 'function' ) { onComplete( failedCount ); }
				} else {
					appendLog( $progress, '✓ All posts processed successfully!' );
					showSuccess( 'CPT import completed! ' + totalPosts + ' posts processed.' );
					if ( typeof onComplete === 'function' ) { onComplete( 0 ); }
				}
				return;
			}

			if ( isProcessing ) { return; }
			isProcessing = true;

			var post           = posts[ currentIndex ];
			var fileLabel2     = totalFiles > 1 ? ' — File ' + fileIndex + '/' + totalFiles : '';

			appendLog( $progress, '📝 Processing: ' + ( post.post_title || '(no title)' ) );

			$.ajax( {
				url:     cfg.ajax_url,
				type:    'POST',
				timeout: 90000,
				data: {
					action:                  'peim_import_cpt_post',
					nonce:                   cfg.nonce,
					post_data:               JSON.stringify( post ),
					check_media_library:     checkMedia,
					download_missing_images: downloadMedia,
					force_status:            post._force_status || 'original',
				},
				success: function ( res ) {
					var title = post.post_title || '(no title)';
					if ( res.success ) {
						if ( res.data.status === 'skipped' ) {
							appendLog( $progress, '⚠ Skipped: ' + title + ' (' + ( res.data.message || '' ) + ')' );
						} else if ( res.data.status === 'updated' ) {
							appendLog( $progress, '🔄 Updated: ' + title + ' (' + ( res.data.message || '' ) + ')' );
						} else {
							appendLog( $progress, '✓ Imported: ' + title );
						}
					} else {
						failedPosts.push( post );
						appendLog( $progress, '✗ Failed: ' + title + ' — ' + ( res.data && res.data.message ? res.data.message : 'Error' ), true );
					}
				},
				error: function ( xhr, status ) {
					failedPosts.push( post );
					appendLog( $progress, '✗ ' + ( status === 'timeout' ? 'Timeout' : 'Error' ) + ': ' + ( post.post_title || '(no title)' ), true );
				},
				complete: function () {
					isProcessing = false;
					currentIndex++;
					var pct = Math.round( ( currentIndex / totalPosts ) * 100 );
					$fill.css( 'width', pct + '%' );
					$text.text( 'Processing: ' + currentIndex + ' of ' + totalPosts + ' posts (' + pct + '%)' + fileLabel2 );
					setTimeout( processNextPost, 500 );
				},
			} );
		}

		processNextPost();
	}

	// =========================================================================
	// IMPORT: BATCH MODE (mirrors batchImportPosts in admin-batch.js)
	// =========================================================================

	function cptBatchImportPosts( posts, fileLabel, fileIndex, totalFiles, checkMedia, downloadMedia, onComplete ) {
		var $progress    = $( '#peiwm-cpt-import-progress' );
		var $fill        = $progress.find( '.peiwm-progress-fill' );
		var $text        = $progress.find( '.peiwm-progress-text' );
		var $log         = $progress.find( '.peiwm-log' );

		$progress.show();
		$fill.css( 'width', '0%' );
		$log.empty();

		var batchSize          = cfg.batch_size          ? parseInt( cfg.batch_size, 10 )          : 20;
		var rawConcurrent      = cfg.concurrent_requests ? parseInt( cfg.concurrent_requests, 10 ) : 3;
		var concurrentRequests = Math.min( rawConcurrent, 3 ); // hard cap at 3 like admin-batch.js
		var batchDelay         = cfg.batch_delay         ? parseInt( cfg.batch_delay, 10 )         : 500;
		var totalPosts         = posts.length;
		var totalBatches       = Math.ceil( totalPosts / batchSize );
		var currentBatch       = 0;
		var processedCount     = 0;
		var failedPosts        = [];
		var completed          = false;
		var myGeneration       = ( window._peiwmCptBatchGen = ( window._peiwmCptBatchGen || 0 ) + 1 );
		var startTime          = Date.now();

		// Time-tracking info bar
		if ( ! $( '#peiwm-cpt-batch-time-info' ).length ) {
			$progress.find( '.peiwm-progress-bar' ).after( '<div id="peiwm-cpt-batch-time-info" style="margin-top:10px;padding:10px;background:#f0f6fc;border-radius:4px;font-size:13px;"></div>' );
		}
		var $timeInfo = $( '#peiwm-cpt-batch-time-info' );

		appendLog( $progress, '📦 Batch import: ' + totalPosts + ' posts in ' + totalBatches + ' batches' + ( totalFiles > 1 ? ' — File ' + fileIndex + '/' + totalFiles : '' ) );
		appendLog( $progress, '⚡ Processing ' + concurrentRequests + ' posts simultaneously' );
		$text.text( 'Starting batch import…' );

		function updateTimeInfo() {
			var elapsed    = Math.floor( ( Date.now() - startTime ) / 1000 );
			var elMin      = Math.floor( elapsed / 60 );
			var elSec      = elapsed % 60;
			var remaining  = processedCount > 0 ? Math.floor( ( elapsed / processedCount ) * ( totalPosts - processedCount ) ) : 0;
			var remMin     = Math.floor( remaining / 60 );
			var remSec     = remaining % 60;
			$timeInfo.html(
				'<strong>⏱️ Time:</strong> Elapsed: ' + elMin + 'm ' + elSec + 's' +
				( processedCount > 0 ? ' | Remaining: ~' + remMin + 'm ' + remSec + 's' : '' ) +
				' | <strong>📊 Status:</strong> ' + processedCount + ' of ' + totalPosts + ' posts completed' +
				' | <strong>🚀 Speed:</strong> ' + ( processedCount > 0 ? ( processedCount / ( elapsed || 1 ) ).toFixed( 1 ) : '0' ) + ' posts/sec'
			);
		}

		function processNextBatch() {
			if ( completed ) { return; }
			if ( currentBatch >= totalBatches ) {
				completed = true;
				$fill.css( 'width', '100%' );
				$text.text( 'Import complete!' );
				updateTimeInfo();
				appendLog( $progress, '✓ All batches imported successfully!' );

				if ( failedPosts.length > 0 ) {
					var failedCount = failedPosts.length;
					appendLog( $progress, '⚠ ' + failedCount + ' post(s) failed.', true );
					var $retryBtn = $( '<button type="button" class="button peiwm-retry-failed-btn" style="margin-top:0.75rem;background:#f97316;color:#fff;border-color:#f97316;">🔄 Retry ' + failedCount + ' failed post(s)</button>' );
					$log.after( $retryBtn );
					$retryBtn.on( 'click', function () {
						$retryBtn.remove();
						var retryData = failedPosts.splice( 0 );
						cptBatchImportPosts( retryData, fileLabel, fileIndex, totalFiles, checkMedia, downloadMedia, onComplete );
					} );
					showSuccess( 'Batch import done! ' + totalPosts + ' processed. ' + failedCount + ' need retry.' );
					if ( typeof onComplete === 'function' ) { onComplete( failedCount ); }
				} else {
					showSuccess( 'Batch import completed! ' + totalPosts + ' posts processed.' );
					if ( typeof onComplete === 'function' ) { onComplete( 0 ); }
				}
				return;
			}

			var startIdx   = currentBatch * batchSize;
			var endIdx     = Math.min( startIdx + batchSize, totalPosts );
			var batchPosts = posts.slice( startIdx, endIdx );
			var batchNum   = currentBatch + 1;

			appendLog( $progress, '📝 Processing batch ' + batchNum + '/' + totalBatches + ' (' + batchPosts.length + ' posts)...' );

			var batchProcessed = 0;
			var batchImported  = 0;
			var batchSkipped   = 0;
			var batchFailed    = 0;
			var batchDone      = false;
			var activeRequests = 0;
			var currentIndex   = 0;

			function processNextPost() {
				while ( activeRequests < concurrentRequests && currentIndex < batchPosts.length ) {
					var post = batchPosts[ currentIndex ];
					currentIndex++;
					activeRequests++;

					var requestFailed = false;

					$.ajax( {
						url:     cfg.ajax_url,
						type:    'POST',
						timeout: 60000,
						data: {
							action:                  'peim_import_cpt_post',
							nonce:                   cfg.nonce,
							post_data:               JSON.stringify( post ),
							check_media_library:     checkMedia,
							download_missing_images: downloadMedia,
							force_status:            post._force_status || 'original',
						},
						success: function ( res ) {
							var title = post.post_title || '(no title)';
							if ( res.success ) {
								if ( res.data.status === 'skipped' ) {
									batchSkipped++;
									appendLog( $progress, '  ⚠ Skipped: ' + title );
								} else if ( res.data.status === 'updated' ) {
									batchImported++;
									appendLog( $progress, '  🔄 Updated: ' + title );
								} else {
									batchImported++;
									appendLog( $progress, '  ✓ Imported: ' + title );
								}
							} else {
								requestFailed = true;
								batchFailed++;
								failedPosts.push( post );
								appendLog( $progress, '  ✗ Failed: ' + title, true );
							}
						},
						error: function ( xhr, status ) {
							requestFailed = true;
							batchFailed++;
							failedPosts.push( post );
							var errMsg = status === 'timeout' ? 'timeout (server busy)' : ( xhr.status === 502 ? '502 Bad Gateway' : status );
							appendLog( $progress, '  ✗ Error: ' + ( post.post_title || '(no title)' ) + ' — ' + errMsg, true );
						},
						complete: function () {
							if ( window._peiwmCptBatchGen !== myGeneration ) { return; }
							activeRequests--;
							batchProcessed++;

							var totalSoFar  = Math.min( processedCount + batchProcessed, totalPosts );
							var pct         = Math.round( ( totalSoFar / totalPosts ) * 100 );
							$fill.css( 'width', pct + '%' );
							$text.text( 'Processing: ' + totalSoFar + ' of ' + totalPosts + ' posts (' + pct + '%) — Batch ' + batchNum + '/' + totalBatches );
							updateTimeInfo();

							if ( batchProcessed >= batchPosts.length && ! batchDone ) {
								batchDone = true;
								appendLog( $progress, '✓ Batch ' + batchNum + ' complete: ' + batchImported + ' imported, ' + batchSkipped + ' skipped, ' + batchFailed + ' failed' );
								currentBatch++;
								processedCount += batchPosts.length;
								var delay = batchFailed > 0 ? Math.max( batchDelay, 1500 ) : batchDelay;
								setTimeout( processNextBatch, delay );
							} else if ( batchProcessed < batchPosts.length ) {
								processNextPost();
							}
						},
					} );
				}
			}

			processNextPost();
		}

		processNextBatch();
	}

} )( jQuery );
