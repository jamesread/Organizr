<?php
$GLOBALS['organizrPages'][] = 'settings_image_manager';
function get_page_settings_image_manager($Organizr)
{
	if (!$Organizr) {
		$Organizr = new Organizr();
	}
	if ((!$Organizr->hasDB())) {
		return false;
	}
	if (!$Organizr->qualifyRequest(1, true)) {
		return false;
	}
	return '
<script>
	buildImageManagerView();
    var myDropzone = new Dropzone("#new-image-form", {
      url: "api/v2/image",
      headers:{ "formKey": local("g","formKey") },
      init: function() {
        this.on("complete", function(file) {
            buildImageManagerView();
            //$.magnificPopup.close();
        });
      }
    });
</script>
<div class="panel bg-org panel-info">
    <div class="panel-heading">
		<span lang="en">View Images</span>
        <button type="button" class="btn btn-info btn-circle pull-right popup-with-form m-r-5" href="#new-image-form" data-effect="mfp-3d-unfold"><i class="fa fa-upload"></i> </button>
	</div>
    <div class="panel-wrapper collapse in" aria-expanded="true">
        <div class="panel-body bg-org" >
        	<div id="gallery-content">
                <div id="gallery-content-center" class="settings-image-manager-list"></div>
            </div>
        </div>
    </div>
</div>
<form action="#" id="new-image-form" class="mfp-hide white-popup-block mfp-with-anim dropzone" enctype="multipart/form-data">
    <h1 lang="en">Upload Image</h1>
    <div class="fallback">
        <input name="file" type="file" multiple />
    </div>
    <div class="clearfix"></div>
</form>
';
}