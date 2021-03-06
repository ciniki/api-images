<?php
//
// Description
// -----------
// This method will check the images are stored in ciniki-storage and then it
// will clear the image blob from the datbase.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_images_dbCheckImageStorage($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'clear'=>array('required'=>'no', 'default'=>'no', 'name'=>'Clear DB Blob Content'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to tnid as owner, or sys admin
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'checkAccess');
    $rc = ciniki_images_checkAccess($ciniki, $args['tnid'], 'ciniki.images.dbIntegrityCheck', 0, 0);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the tenant storage directory
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'storageDir');
    $rc = ciniki_tenants_hooks_storageDir($ciniki, $args['tnid'], array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $tenant_storage_dir = $rc['storage_dir'];
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFixTableHistory');

    $strsql = "SELECT id, uuid "
        . "FROM ciniki_images "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
//        . "AND image <> '' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.images', 'image');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['rows']) ) { 
        return array('stat'=>'ok');
    }

    //
    // Got through the images and check all of them are stored in the storage directory
    //
    $images = $rc['rows'];
    $missing = '';
    foreach($images as $image) {
        $storage_filename = $tenant_storage_dir . '/ciniki.images/' . $image['uuid'][0] . '/' . $image['uuid'];
        if( !file_exists($storage_filename) ) {
            $missing .= ($missing != '' ? ', ' : '') . $image['uuid'];
            continue;
        }
        if( isset($args['clear']) && $args['clear'] == 'yes' && file_exists($storage_filename) ) {
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.images.image', $image['id'], array('image'=>''));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
    }

    if( $missing != '' ) {  
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.images.137', 'msg'=>"Missing the following images in ($tenant_storage_dir): $missing"));
    }

    return array('stat'=>'ok');
}
?>
