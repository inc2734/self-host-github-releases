<?php
include_once( __DIR__ . '/config.php' );

if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		$headers = [];
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
				$key = substr( $name, 5 );
				$key = str_replace( '_', ' ', $key );
				$key = strtolower( $key );
				$key = ucwords( $key );
				$key = str_replace( ' ', '-', $key );
				$headers[ $key ] = $value;
			}
		}
		return $headers;
	}
}

/**
 * Return true when the request is GitHub Webhooks
 *
 * @param json $data
 * @return boolean
 */
function is_github_webhooks_request( $data ) {
	$header = getallheaders();
	$hmac   = hash_hmac( 'sha1', $data, SECRET_KEY );

	return isset( $header['X-Hub-Signature'] ) && 'sha1=' . $hmac === $header['X-Hub-Signature'];
}

/**
 * Return zip url of latest release
 *
 * @param json $data
 * @return string|false
 */
function get_remote_zip_url( $data ) {
	$release = get_release_data( $data );
	if ( ! $release ) {
		return false;
	}

	if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
		if ( ! empty( $release->assets[0] ) && is_object( $release->assets[0] ) ) {
			if ( ! empty( $release->assets[0]->browser_download_url ) ) {
				return $release->assets[0]->browser_download_url;
			}
		}
	}

	return false;
}

/**
 * Return the release package directory path
 *
 * @param string $tag_name
 * @return string
 */
function get_package_dir_path( $tag_name ) {
	return __DIR__ . '/' . RELEASES_DIR_NAME . '/' . $tag_name;
}

/**
 * Create the reelase package directory
 *
 * @param string $tag_name
 * @return boolean
 */
function create_package_dir( $tag_name ) {
	$is_success = false;
	$dir = get_package_dir_path( $tag_name );

	if ( file_exists( $dir ) ) {
		if ( is_writable( $dir ) ) {
			return true;
		}

		return chmod( $dir, 0755 );
	}

	$is_success = mkdir( $dir, 0755, true );
	if ( $is_success ) {
		$is_success = chmod( $dir, 0755 );
	}

	return $is_success;
}

/**
 * Save the reelase package
 *
 * @param string $save_dir
 * @param string $remote_zip_url
 * @return boolean
 */
function save_zip( $save_dir, $remote_zip_url ) {
	$zip = $save_dir . '/' . ZIP_FILE_NAME;
	if ( file_exists( $zip ) ) {
		$renamed = rename( $zip, $zip . '.rename' );
		if ( ! $renamed ) {
			return false;
		}
	}

	$src        = fopen( $remote_zip_url, 'rb' );
	$dst        = fopen( $zip, 'wb' );
	$bytes      = stream_copy_to_stream( $src, $dst );
	$is_created = ! empty( $bytes );

	if ( ! $is_created ) {
		if ( file_exists( $zip . '.rename' ) ) {
			$renamed = rename( $zip . '.rename', $zip );
			if ( ! $renamed ) {
				return false;
			}
		}
	}

	return $is_created;
}

/**
 * Return latest response.json path
 *
 * @return string
 */
function get_latest_response_json_path() {
	return __DIR__ . '/response.json';
}

/**
 * Return saved zip url
 *
 * @param string $tag_name
 * @return string
 */
function get_zip_url( $tag_name ) {
	return INSTALLATION_URL . '/' . RELEASES_DIR_NAME . '/' . $tag_name . '/' . ZIP_FILE_NAME;
}

/**
 * Create response.json
 *
 * @param json $data
 * @return boolean
 */
function create_response_json( $data ) {
	$release = get_release_data( $data );
	if ( ! $release ) {
		return false;
	}

	if ( ! get_remote_zip_url( $data ) ) {
		return false;
	}

	$latest_response_file = get_latest_response_json_path();
	if ( ! file_exists( $latest_response_file ) ) {
		if ( false === file_put_contents( $latest_response_file, '', LOCK_EX ) ) {
			return false;
		}

		if ( false === chmod( $latest_response_file, 0644 ) ) {
			return false;
		}
	}

	$response_file = get_package_dir_path( $release->tag_name ) . '/response.json';
	if ( ! file_exists( $response_file ) ) {
		if ( false === file_put_contents( $response_file, '', LOCK_EX ) ) {
			return false;
		}

		if ( false === chmod( $response_file, 0644 ) ) {
			return false;
		}
	}

	$new_zip_url = get_zip_url( $release->tag_name );

	$release->assets[0]->browser_download_url = $new_zip_url;

	$latest_bytes = file_put_contents(
		$latest_response_file,
		json_encode( $release, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
		LOCK_EX
	);

	$bytes = file_put_contents(
		$response_file,
		json_encode( $release, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
		LOCK_EX
	);

	return ! empty( $latest_bytes ) && ! empty( $bytes );
}

/**
 * Return release data object
 *
 * @param json $data
 * @return object|false
 */
function get_release_data( $data ) {
	$data = json_decode( $data );
	if ( ! isset( $data->action ) || 'released' !== $data->action ) {
		return false;
	}

	if ( ! isset( $data->release ) ) {
		return false;
	}

	if ( ! empty( $data->release->assets ) && is_array( $data->release->assets ) ) {
		return$data->release;
	}

	$context = stream_context_create(
		[
			'http' => [
				'method' => 'GET',
				'header' => [
					'Authorization: token ' . ACCESS_TOKEN,
					'User-Agent: ' . GITHUB_USER . '/' . GITHUB_REPOSITORY,
					'Content-type: application/json; charset=UTF-8',
				],
			],
		]
	);

	$data = file_get_contents(
		'https://api.github.com/repos/' . GITHUB_USER . '/' . GITHUB_REPOSITORY . '/releases/latest',
		false,
		$context
	);

	return json_decode( $data );
}

/**
 * Remove github release Package
 *
 * @param json $data
 * @return boolean
 */
function remove_github_release( $data ) {
	$release = get_release_data( $data );
	if ( ! $release ) {
		return false;
	}

	if ( ! isset( $release->id ) ) {
		return false;
	}

	$release_id  = $release->id;
	$request_url = 'https://api.github.com/repos/' . GITHUB_USER . '/' . GITHUB_REPOSITORY . '/releases/' . $release_id;

	$ch = curl_init( $request_url );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, [
		'Authorization: token ' . ACCESS_TOKEN,
		'User-Agent: ' . GITHUB_USER . '/' . GITHUB_REPOSITORY,
	] );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );

	$is_success = curl_exec( $ch );
	curl_close( $ch );

	return false !== $is_success;
}
