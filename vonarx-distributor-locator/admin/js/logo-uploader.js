(function () {
	function initUploader( container ) {
		var dropzone = container.querySelector( '.vonarx-logo-dropzone' );
		var fileInput = container.querySelector( '.vonarx-logo-file-input' );
		var preview = container.querySelector( '.vonarx-logo-preview' );
		var placeholderText = container.querySelector( '.vonarx-logo-placeholder-text' );
		var removeBtn = container.querySelector( '.vonarx-logo-remove' );
		var removeFlag = container.querySelector( '.vonarx-logo-remove-flag' );

		if ( ! dropzone || ! fileInput || ! preview ) {
			return;
		}

		dropzone.addEventListener( 'click', function () {
			fileInput.click();
		} );

		fileInput.addEventListener( 'change', function () {
			var file = fileInput.files && fileInput.files[ 0 ];
			if ( ! file ) {
				return;
			}

			var objectUrl = URL.createObjectURL( file );
			preview.src = objectUrl;
			preview.style.display = '';
			if ( placeholderText ) {
				placeholderText.style.display = 'none';
			}
			if ( removeBtn ) {
				removeBtn.style.display = '';
			}
			if ( removeFlag ) {
				removeFlag.value = '0';
			}
		} );

		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				fileInput.value = '';
				preview.removeAttribute( 'src' );
				preview.style.display = 'none';
				if ( placeholderText ) {
					placeholderText.style.display = '';
				}
				removeBtn.style.display = 'none';
				if ( removeFlag ) {
					removeFlag.value = '1';
				}
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.vonarx-logo-uploader' ).forEach( initUploader );
	} );
})();
