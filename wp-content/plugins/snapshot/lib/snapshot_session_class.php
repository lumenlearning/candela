<?php
/*
Snapshots Session Class
Author: Paul Menard (Incsub)
Dexcription: This session class is used in place of PHP _SESSION variable since many sites don't see to have _SESSIONS setup properly or not at all
*/

if (!class_exists('SnapshotSessions')) {
	class SnapshotSessions {

		var $DEBUG;
		var $sessionFileFull;
		var $force_clear = false;
		var $data = array();

		function __construct($backupLogFolderFull, $item_key, $force_clear = false) {
			$backupLogFolderFull 	= trailingslashit($backupLogFolderFull);
			$item_key				= esc_attr($item_key);

			$this->force_clear = $force_clear;

			$this->sessionFileFull = $backupLogFolderFull . $item_key ."_session" .".php";

			$this->load_session();
			return $this->data;
		}

	    function SnapshotSessions($backupLogFolderFull, $item_key, $force_clear = false) {
	        $this->__construct($backupLogFolderFull, $item_key, $force_clear = false);
	    }

		function __destruct() {
			$this->save_session();
		}

		function load_session() {

			if ((file_exists($this->sessionFileFull)) && ($this->force_clear == false)) {
				$data = file_get_contents($this->sessionFileFull);
				if ($data) {
					if (is_serialized($data)) {
						$this->data = unserialize($data);
					}
				} else {
					$this->data = array();
				}
			} else {
				$this->data = array();
			}
		}

		function save_session() {
			if (!isset($this->data))
				$this->data = array();

			$data = serialize($this->data);
			file_put_contents($this->sessionFileFull, $data);
		}

		function update_data($data) {
			$this->data = $data;

			if (is_array($this->data))
				$this->data = serialize($this->data);

		}

	}
}