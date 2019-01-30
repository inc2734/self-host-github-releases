<?php
include_once( __DIR__ . '/lib.php' );

$data = file_get_contents( "php://input" );

if ( ! is_github_webhooks_request( $data ) ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' Invalid access: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

$release = get_release_data( $data );
if ( ! $release ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' No release response: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

$remote_zip_url = get_remote_zip_url( $data );
if ( ! $remote_zip_url ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' Remote zip file is not exist: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

$is_created_package_dir = create_package_dir( $release->tag_name );
if ( ! $is_created_package_dir ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' Package directory can not created: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

$is_saved_zip = save_zip( get_package_dir_path( $release->tag_name ), $remote_zip_url );
if ( ! $is_saved_zip ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' zip file can not created: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

$is_created_response_json = create_response_json( $data );
if ( ! $is_created_response_json ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' response.json is not writable: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}

/*
$is_removed = remove_github_release( $data );
if ( ! $is_removed ) {
	error_log( date( '[Y-m-d H:i:s]' ) . ' GitHub release package can not removed: ' . $_SERVER['REMOTE_ADDR'] );
	exit;
}
*/
