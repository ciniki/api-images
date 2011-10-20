<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Info
// ----
// Status: 			defined
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// 
// Returns
// -------
//
function moss_images_getImage($moss, $business_id, $image_id, $version, $maxlength) {

	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');

	//
	// Get the modification information for this image
	// The business_id is required to ensure a bug doesn't allow an image from another business.
	//
	$strsql = "SELECT images.date_added, images.last_updated, UNIX_TIMESTAMP(image_versions.last_updated) as last_updated "
		. "FROM images, image_versions "
		. "WHERE images.id = '" . moss_core_dbQuote($moss, $image_id) . "' "
		. "AND images.business_id = '" . moss_core_dbQuote($moss, $business_id) . "' "
		. "AND images.id = image_versions.image_id "
		. "AND image_versions.version = '" . moss_core_dbQuote($moss, $version) . "' ";
	$rc = moss_core_dbHashQuery($moss, $strsql, 'images', 'image');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'341', 'msg'=>'Unable to render image', 'err'=>$rc['err']));
	}
	if( !isset($rc['image']) ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'342', 'msg'=>'Unable to render image'));
	}

	

	//
	// Check headers and to see if browser has cached version.  
	//
	if( isset($moss['request']['If-Modified-Since']) != '' 
		&& strtotime($moss['request']['If-Modified-Since']) >= $rc['image']['last_updated'] ) {
	    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $rc['image']['last_updated']) . ' GMT', true, 304);
		error_log("Cache ok");
		return array('stat'=>'ok');
	}


	//
	// FIXME: Check the cache for a current copy
	//


	//
	// Pull the image from the database
	//
	require_once($moss['config']['core']['modules_dir'] . '/images/private/renderImage.php');
	return moss_images_renderImage($moss, $image_id, $version, $maxlength);
}

//		Found on: http://ernieleseberg.com/2009/php-image-output-and-browser-caching/
//		
//		// Return the requested graphic file to the browser
//		// or a 304 code to use the cached browser copy
//		function displayGraphicFile ($graphicFileName, $fileType='jpeg') {
//		  $fileModTime = filemtime($graphicFileName);
//		  // Getting headers sent by the client.
//		  $headers = getRequestHeaders();
//		  // Checking if the client is validating his cache and if it is current.
//		  if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == $fileModTime)) {
//		
//		    // Client's cache IS current, so we just respond '304 Not Modified'.
//		    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModTime).' GMT', true, 304);
//		  } else {
//		    // Image not cached or cache outdated, we respond '200 OK' and output the image.
//		    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fileModTime).' GMT', true, 200);
//		    header('Content-type: image/'.$fileType);
//		    header('Content-transfer-encoding: binary');
//		    header('Content-length: '.filesize($graphicFileName));
//		    readfile($graphicFileName);
//		  }
//		}
//		The second function to get the header request details. We specifically require the ‘If-Modified-Since’ header.
//		
//		// return the browser request header
//		// use built in apache ftn when PHP built as module,
//		// or query $_SERVER when cgi
//		function getRequestHeaders() {
//		  if (function_exists("apache_request_headers")) {
//		    if($headers = apache_request_headers()) {
//		      return $headers;
//		
//		    }
//		  }
//		  $headers = array();
//		  // Grab the IF_MODIFIED_SINCE header
//		  if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
//		    $headers['If-Modified-Since'] = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
//		  }
//		  return $headers;
//		}
?>
