<?php

/**
 * Dropbox API class
 *
 * @package Dropbox
 * @copyright Copyright (C) 2010 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/dropbox-php/wiki/License MIT
 */
class Dropbox_API {

    /**
     * Sandbox root-path
     */
    const ROOT_SANDBOX = 'sandbox';

    /**
     * Dropbox root-path
     */
    const ROOT_DROPBOX = 'dropbox';

    /**
     * API URl
     */
    protected $api_url = 'https://api.dropbox.com/1/';

    /**
     * Content API URl
     */
    protected $api_content_url = 'https://api-content.dropbox.com/1/';

    /**
     * OAuth object
     *
     * @var Dropbox_OAuth
     */
    protected $oauth;

	// Instance of the Snapshot logger we need for debugging.
	protected $logger;

    /**
     * Default root-path, this will most likely be 'sandbox' or 'dropbox'
     *
     * @var string
     */
    protected $root;
    protected $useSSL;


	var $last_result = array();

    /**
     * Constructor
     *
     * @param Dropbox_OAuth Dropbox_Auth object
     * @param string $root default root path (sandbox or dropbox)
     */
    public function __construct(Dropbox_OAuth $oauth, $root = self::ROOT_DROPBOX, $useSSL = true) {

        $this->oauth = $oauth;
        $this->root = $root;
        $this->useSSL = $useSSL;
        if (!$this->useSSL)
        {
            throw new Dropbox_Exception('Dropbox REST API now requires that all requests use SSL');
        }

    }

    /**
     * Returns information about the current dropbox account
     *
     * @return stdclass
     */
    public function getAccountInfo() {

        $data = $this->oauth->fetch($this->api_url . 'account/info');
        return json_decode($data['body'],true);

    }

    /**
     * Returns a file's contents
     *
     * @param string $path path
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return string
     */
    public function getFile($path = '', $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $result = $this->oauth->fetch($this->api_content_url . 'files/' . $root . '/' . ltrim($path,'/'));
        return $result['body'];

    }

    /**
     * Uploads a new file
     *
     * @param string $path Target path (including filename)
     * @param string $file Either a path to a file or a stream resource
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return bool
     */
    public function putFile($path, $file, $progress_callback=null, $logger=null) {

		if ($logger)
			$this->logger = $logger;

		$path = str_replace('\\', '/', stripslashes($path));
		$path = str_replace('//', '/', $path);
		//$this->logger->log_message('DEBUG: path['. $path .'] file['. $file .']');

        $filesize = filesize($file);
        if ($filesize > 4194304) {	// 41943040
			return $this->putFileChunked($path, $file, $progress_callback);
		} else {

	        $directory = dirname($path);
	        $filename = basename($path);

	        if($directory==='.') $directory = '';
	        $directory = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($directory));
	        $filename = str_replace('~', '%7E', rawurlencode($filename));

	        if (is_string($file)) {
	            $file = fopen($file,'rb');
	        } elseif (!is_resource($file)) {
	            throw new Dropbox_Exception('File must be a file-resource or a string');
	        }

			$uri = $this->api_content_url . 'files/' . $this->root . '/' . trim($directory,'/');
			if (strlen($filename))
				$uri.='?file=' . $filename;

	        $this->last_result = $this->multipartFetch($uri, $file, $filename);

	        if(!isset($this->last_result["httpStatus"]) || $this->last_result["httpStatus"] != 200)
	            throw new Dropbox_Exception("Uploading file to Dropbox failed");

			$this->last_result['body'] = json_decode($this->last_result['body'], true);
			$this->last_result['status'] = true;
	        return true;
		}
    }

    public function putFileChunked($destination_path_file, $dir_file_to_send, $progress_callback=null, $logger=null) {

		if ($logger)
			$this->logger = $logger;

        $destination_directory 	= dirname($destination_path_file);
        $destination_filename 	= basename($destination_path_file);

		//$this->logger->log_message('DEBUG: '. __FUNCTION__ .': destination_directory['. $destination_directory .'] destination_filename['. $destination_filename .']');


        if($destination_directory === '.') $destination_directory = '';
        $destination_directory = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($destination_directory));

        $destination_filename = str_replace('~', '%7E', rawurlencode($destination_filename));
        if (is_null($root)) $root = $this->root;

		$file_uploadid = '';
		$file_offset = 0;

        $file_h = fopen($dir_file_to_send, 'rb');
		if (!$file_h) {
			echo "cannot open file: ". $dir_file_to_send ."<br />";
			return;
		}

		$headers = array(
            'Content-Type' => 'application/octet-stream;',
        );

		$file_uploadid = '';
		$file_offset = 0;
      	$file_size = filesize($dir_file_to_send);
		if (isset($progress_callback)) {
        	call_user_func_array($progress_callback, array(array('file_offset' => $file_offset, 'file_uploadid' => $file_uploadid)));
		}


        while ($file_data = fread($file_h, 4194304)) {  //4MB chunks. Can be larger up to 150Mb
			$this->logger->log_message('Sending file chunked. Offset: '.  $file_offset .'/'.  $file_size .' ('. intval(($file_offset/$file_size)*100) .'%)');

			$url = $this->api_content_url . 'chunked_upload?offset='. $file_offset;
			if (!empty($file_uploadid))
				$url .= '&upload_id='. $file_uploadid;

			$result = $this->oauth->fetch($url, $file_data, 'POST', $headers);
	        if (( !isset($result["httpStatus"])) || ($result["httpStatus"] != 200)) {
				$this->logger->log_message('ERROR: result<pre>'. print_r($result, true). '</pre>');

	            throw new Dropbox_Exception("Uploading file to Dropbox failed");
			}
			$result_body = json_decode($result['body'], true);
			//echo "result_body<pre>"; print_r($result_body); echo "</pre>";
			if ((isset($result_body['offset'])) && ($result_body['offset'] > 0)) {
				$file_offset 	= $result_body['offset'];
				$file_uploadid 	= $result_body['upload_id'];

				if (isset($progress_callback)) {
                	call_user_func_array($progress_callback, array(array('file_offset' => $file_offset, 'file_uploadid' => $file_uploadid)));
				}
				fseek($file_h, $file_offset);
			}
		}
		fclose($file_data_h);

		// Now commit the chunked transaction
		$url_path = trailingslashit($this->root) . trailingslashit($destination_directory) . $destination_filename .'?upload_id='. $file_uploadid;
		$this->logger->log_message('DEBUG: '. __FUNCTION__ .': url_path['. $url_path .']');

		$url_path = str_replace('\\', '/', stripslashes($url_path));
		$url_path = str_replace('//', '/', $url_path);

		$this->logger->log_message('Sending file chunked commit url path: '. $url_path);
		$url = trailingslashit($this->api_content_url) . trailingslashit('commit_chunked_upload') . $url_path;
		$this->logger->log_message('Sending file chunked commit url: '. $url);
        $this->last_result = $this->oauth->fetch($url, array(), 'POST');
		$this->logger->log_message('DEBUG: '. __FUNCTION__ .': last_result<pre>'. print_r($this->last_result, true) .'</pre>');
        //if(!isset($this->last_result["httpStatus"]) || $this->last_result["httpStatus"] != 200)
        //    throw new Dropbox_Exception("Uploading file to Dropbox failed");

//		$this->last_result['body'] = json_decode($this->last_result['body'], true);
		$this->last_result['status'] = true;

        return true;
    }


    /**
     * Copies a file or directory from one location to another
     *
     * This method returns the file information of the newly created file.
     *
     * @param string $from source path
     * @param string $to destination path
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return stdclass
     */
    public function copy($from, $to, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->oauth->fetch($this->api_url . 'fileops/copy', array('from_path' => $from, 'to_path' => $to, 'root' => $root));

        return json_decode($response['body'],true);

    }

    /**
     * Creates a new folder
     *
     * This method returns the information from the newly created directory
     *
     * @param string $path
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return stdclass
     */
    public function createFolder($path, $root = null) {

        if (is_null($root)) $root = $this->root;

        // Making sure the path starts with a /
        $path = '/' . ltrim($path,'/');

        $response = $this->oauth->fetch($this->api_url . 'fileops/create_folder', array('path' => $path, 'root' => $root),'POST');
        return json_decode($response['body'],true);

    }

    /**
     * Deletes a file or folder.
     *
     * This method will return the metadata information from the deleted file or folder, if successful.
     *
     * @param string $path Path to new folder
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return array
     */
    public function delete($path, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->oauth->fetch($this->api_url . 'fileops/delete', array('path' => $path, 'root' => $root));
        return json_decode($response['body']);

    }

    /**
     * Moves a file or directory to a new location
     *
     * This method returns the information from the newly created directory
     *
     * @param mixed $from Source path
     * @param mixed $to destination path
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return stdclass
     */
    public function move($from, $to, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->oauth->fetch($this->api_url . 'fileops/move', array('from_path' => rawurldecode($from), 'to_path' => rawurldecode($to), 'root' => $root));

        return json_decode($response['body'],true);

    }

    /**
     * Returns file and directory information
     *
     * @param string $path Path to receive information from
     * @param bool $list When set to true, this method returns information from all files in a directory. When set to false it will only return infromation from the specified directory.
     * @param string $hash If a hash is supplied, this method simply returns true if nothing has changed since the last request. Good for caching.
     * @param int $fileLimit Maximum number of file-information to receive
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return array|true
     */
    public function getMetaData($path, $list = true, $hash = null, $fileLimit = null, $root = null) {

        if (is_null($root)) $root = $this->root;

        $args = array(
            'list' => $list,
        );

        if (!is_null($hash)) $args['hash'] = $hash;
        if (!is_null($fileLimit)) $args['file_limit'] = $fileLimit;

        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->oauth->fetch($this->api_url . 'metadata/' . $root . '/' . ltrim($path,'/'), $args);

        /* 304 is not modified */
        if ($response['httpStatus']==304) {
            return true;
        } else {
            return json_decode($response['body'],true);
        }

    }

    /**
    * A way of letting you keep up with changes to files and folders in a user's Dropbox. You can periodically call /delta to get a list of "delta entries", which are instructions on how to update your local state to match the server's state.
    *
    * This method returns the information from the newly created directory
    *
    * @param string $cursor A string that is used to keep track of your current state. On the next call pass in this value to return delta entries that have been recorded since the cursor was returned.
    * @return stdclass
    */
    public function delta($cursor) {

    	$arg['cursor'] = $cursor;

    	$response = $this->oauth->fetch($this->api_url . 'delta', $arg, 'POST');
    	return json_decode($response['body'],true);

    }

    /**
     * Returns a thumbnail (as a string) for a file path.
     *
     * @param string $path Path to file
     * @param string $size small, medium or large
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return string
     */
    public function getThumbnail($path, $size = 'small', $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->oauth->fetch($this->api_content_url . 'thumbnails/' . $root . '/' . ltrim($path,'/'),array('size' => $size));

        return $response['body'];

    }

    /**
     * This method is used to generate multipart POST requests for file upload
     *
     * @param string $uri
     * @param array $arguments
     * @return bool
     */
    protected function multipartFetch($uri, $file, $filename) {

        /* random string */
		$boundary = md5(date('U'));

        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        $body="--" . $boundary . "\r\n";
        $body.="Content-Disposition: form-data; name=file; filename=".rawurldecode($filename)."\r\n";
        $body.="Content-type: application/octet-stream\r\n";
        $body.="\r\n";
        $body.=stream_get_contents($file);
        $body.="\r\n";
        $body.="--" . $boundary . "--";

        return $this->oauth->fetch($uri, $body, 'POST', $headers);
    }


	/**
     * Search
     *
     * Returns metadata for all files and folders that match the search query.
     *
	 * @added by: diszo.sasil
	 *
     * @param string $query
     * @param string $root Use this to override the default root path (sandbox/dropbox)
	 * @param string $path
     * @return array
     */
	public function search($query = '', $root = null, $path = ''){
		if (is_null($root)) $root = $this->root;
		if(!empty($path)){
			$path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
		}
        $response = $this->oauth->fetch($this->api_url . 'search/' . $root . '/' . ltrim($path,'/'),array('query' => $query));
        return json_decode($response['body'],true);
	}

    /**
     * Creates and returns a shareable link to files or folders.
     *
     * Note: Links created by the /shares API call expire after thirty days.
     *
     * @param type $path
     * @param type $root
     * @return type
     */
    public function share($path, $root = null) {
        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->oauth->fetch($this->api_url.  'shares/'. $root . '/' . ltrim($path, '/'), array(), 'POST');
        return json_decode($response['body'],true);

    }

    /**
    * Returns a link directly to a file.
    * Similar to /shares. The difference is that this bypasses the Dropbox webserver, used to provide a preview of the file, so that you can effectively stream the contents of your media.
    *
    * Note: The /media link expires after four hours, allotting enough time to stream files, but not enough to leave a connection open indefinitely.
    *
    * @param type $path
    * @param type $root
    * @return type
    */
    public function media($path, $root = null) {

    	if (is_null($root)) $root = $this->root;
    	$path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
    	$response = $this->oauth->fetch($this->api_url.  'media/'. $root . '/' . ltrim($path, '/'), array(), 'POST');
    	return json_decode($response['body'],true);

    }

    /**
    * Creates and returns a copy_ref to a file. This reference string can be used to copy that file to another user's Dropbox by passing it in as the from_copy_ref parameter on /fileops/copy.
    *
    * @param type $path
    * @param type $root
    * @return type
    */
    public function copy_ref($path, $root = null) {

    	if (is_null($root)) $root = $this->root;
    	$path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
    	$response = $this->oauth->fetch($this->api_url.  'copy_ref/'. $root . '/' . ltrim($path, '/'));
    	return json_decode($response['body'],true);

    }


}