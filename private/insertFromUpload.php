<?php
//
// Description
// -----------
// This function will insert an image which has been uploaded and parsed into
// the $_FILES section of PHP.  This means the form must be submitted with
// "application/x-www-form-urlencoded".
//
// Info
// ----
// Status: 			alpha
//
// Arguments
// ---------
// business_id:		The ID of the business the photo is attached to.
//
// user_id:			The user_id to attach the photo to.  This may be 
// 					different from the session user, as specified by
// 					the calling function.
//
// upload_file:		The array from $_FILES[upload_field_name].
//
// perms:			The bitmask for permissions for the photo. 
//					*future* default for now is 1 - public.
//
// name:			*optional* The name to give the photo in the database.  If blank
//					The $file['name'] is used as the name of the photo.
//
// caption:			*optional* The caption for the image, may be left blank.
//
// force_duplicate:	If this is set to 'yes' and the image crc32 checksum is found
//					already belonging to this business, the image will still be inserted 
//					into the database.
// 
// Returns
// -------
// The image ID that was added.
//
function moss_images_insertFromUpload($moss, $business_id, $user_id, $upload_file, $perms, $name, $caption, $force_duplicate) {
	//
	// Load the image into Imagick so it can be processed and uploaded
	//
	$image = new Imagick($upload_file['tmp_name']);
	if( $image == null || $image === false ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'306', 'msg'=>'Unable to upload image'));
	}

	$original_filename = $upload_file['name'];
	if( $name == null || $name == '' ) {
		$name = $original_filename;

		if( preg_match('/(IMG|DSC)_[0-9][0-9][0-9][0-9]\.(jpg|gif|tiff|bmp|png)/', $name, $matches) ) {
			// Switch to blank name
			$name = '';
		}

		$name = preg_replace('/(.jpg|.png|.gif|.tiff|.bmp)/i', '', $name);
	}

	$checksum = crc32('' . $image->getImageBlob());

	//
	// Get the type of photo (jpg, png, gif, tiff, bmp, etc)
	//
	$format = strtolower($image->getImageFormat());
	$exif = array();
	$type = 0;
	if( $format == 'jpeg' ) {
		$type = 1;
		$exif = read_exif_data($upload_file['tmp_name']);
	} elseif( $format == 'png' ) {
		$type = 2;
	} elseif( $format == 'gif' ) {
		$type = 3;
	} elseif( $format == 'tiff' ) {
		$type = 4;
		$exif = read_exif_data($upload_file['tmp_name']);
	} elseif( $format == 'bmp' ) {
		$type = 5;
	} else {
		return array('stat'=>'fail', 'err'=>array('code'=>'307', 'msg'=>'Invalid format' . $format));
	}

	//
	// Load photo into blob
	//

	//
	// Add code to check for duplicate image
	//
	$strsql = "SELECT id, title, caption FROM images "
		. "WHERE business_id = '" . moss_core_dbQuote($moss, $business_id) . "' "
		. "AND user_id = '" . moss_core_dbQuote($moss, $user_id) . "' "
		. "AND checksum = '" . moss_core_dbQuote($moss, $checksum) . "' ";
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$rc = moss_core_dbHashQuery($moss, $strsql, 'images', 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'329', 'msg'=>'Unable to check for duplicates', 'err'=>$rc['err']));
	}

	//
	// Check if there is an image that exists, and that the force flag has not been set
	//
	if( isset($rc['images']) && $force_duplicate != 'yes' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'330', 'msg'=>'Duplicate image'));
	}

	//
	// Add to image table
	//
	$strsql = "INSERT INTO images (business_id, user_id, perms, type, original_filename, "
		. "remote_id, title, caption, checksum, date_added, last_updated, image) VALUES ( "
		. "'" . moss_core_dbQuote($moss, $business_id) . "', "
		. "'" . moss_core_dbQuote($moss, $user_id) . "', "
		. "'" . moss_core_dbQuote($moss, $perms) . "', " 
		. "'" . moss_core_dbQuote($moss, $type) . "', "
		. "'" . moss_core_dbQuote($moss, $original_filename) . "', "
		. "0, "
		. "'" . moss_core_dbQuote($moss, $name). "', "
		. "'" . moss_core_dbQuote($moss, $caption). "', "
		. "'" . moss_core_dbQuote($moss, $checksum) . "', "
		. "UTC_TIMESTAMP(), UTC_TIMESTAMP(), "
		. "'" . moss_core_dbQuote($moss, $image->getImageBlob()) . "')";
	require_once($moss['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	$rc = moss_core_dbInsert($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'308', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
	}
	if( !isset($rc['insert_id']) || $rc['insert_id'] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'309', 'msg'=>'Unable to upload image'));	
	}
	$image_id = $rc['insert_id'];

	//
	// Add EXIF information to image_details
	//
	if( $exif !== false ) {
		foreach ($exif as $key => $section) {
			if( is_array($section) ) {
				foreach ($section as $name => $val) {
					$strsql = "INSERT INTO image_details (image_id, detail_key, detail_value, date_added, last_updated"
						. ") VALUES ("
						. "'" . moss_core_dbQuote($moss, $image_id) . "', "
						. "'" . moss_core_dbQuote($moss, "exif.$key.$name") . "', "
						. "'" . moss_core_dbQuote($moss, $val) . "', "
						. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
					$rc = moss_core_dbInsert($moss, $strsql, 'images');
					if( $rc['stat'] != 'ok' ) {
						return array('stat'=>'fail', 'err'=>array('code'=>'313', 'msg'=>'Unable to upload image', 'err'=>$rc['err']));	
					}
				}
			}
		}
	}
	
	//
	// There should always be two version added to the database, an original and thumbnail.
	//
	$thumb_crop_data = '';

	//
	// Determine the size or the original, and the crop area for a thumbnail
	//
	$width = $image->getimagewidth();
	$height = $image->getimageheight();
	if( $width < 1 || $height < 1 ) {
		// Check to make sure there is some size to the image
		return array('stat'=>'fail', 'err'=>array('code'=>'314', 'msg'=>'The image is empty'));
	}

	$flags = 0;
	if( $width < $height ) {
		$flags = 0x01;		// Portrait
		$offset = floor(($height-$width)/2);
		$thumb_crop_data = $width . ',' . $width . ',0,' . $offset;
	} elseif( $width > $height ) {
		$flags = 0x02;		// Landscape
		$offset = floor(($width-$height)/2);
		$thumb_crop_data = $height . ',' . $height . ',' . $offset . ',0';
	} else {
		$flags = 0x03;		// Square
	}

	//
	// Add the original version in the image_versions table
	//
	$strsql = "INSERT INTO image_versions (image_id, version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . moss_core_dbQuote($moss, $image_id) . "', 'original', "
		. moss_core_dbQuote($moss, $flags) . ", UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = moss_core_dbInsert($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'315', 'msg'=>'Unable to store original image', 'err'=>$rc['err']));	
	}

	//
	// Add the thumbnail version into the image_version tables
	//
	$strsql = "INSERT INTO image_versions (image_id, version, flags, date_added, last_updated"
		. ") VALUES ("
		. "'" . moss_core_dbQuote($moss, $image_id) . "', "
		. "'thumbnail', 0x03, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = moss_core_dbInsert($moss, $strsql, 'images');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('code'=>'316', 'msg'=>'Unable to store thumbnail image', 'err'=>$rc['err']));	
	}

	//
	// Insert the crop action into the image_actions table for the thumbnail, if the original was not square
	//
	if( $thumb_crop_data != '' ) {
		$strsql = "INSERT INTO image_actions (image_id, version, sequence, action, params, date_added, last_updated"
			. ") VALUES ("
			. "'" . moss_core_dbQuote($moss, $image_id) . "', "
			. "'thumbnail', 1, 1, "
			. "'" . moss_core_dbQuote($moss, $thumb_crop_data) . "', "
			. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
		$rc = moss_core_dbInsert($moss, $strsql, 'images');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('code'=>'317', 'msg'=>'Unable to crop thumbnail', 'err'=>$rc['err']));	
		}
	}

	return array('stat'=>'ok', 'id'=>$image_id);
}
?>