<?php
/**
 * FusionForge file release system
 *
 * Copyright 2002, Tim Perdue/GForge, LLC
 * Copyright 2009, Roland Mas
 * Copyright (C) 2012 Alain Peyrat - Alcatel-Lucent
 * Copyright 2014, Franck Villaume - TrivialDev
 * Copyright 2016-2023, Henry Kwong, Tod Hing - SimTK Team
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once $gfcommon.'include/FFError.class.php';
require_once $gfcommon.'frs/FRSFile.class.php';

/**
 * Factory method which creates a FRSRelease from an release id
 *
 * @param	int	$release_id	The release id
 * @param	array	$data		The result array, if it's passed in
 * @return	object	FRSRelease object
 */
function frsrelease_get_object($release_id, $data = array()) {
	global $FRSRELEASE_OBJ;
	if (!isset($FRSRELEASE_OBJ['_'.$release_id.'_'])) {
		if ($data) {
			//the db result handle was passed in
		} else {
			$res = db_query_params ('SELECT * FROM frs_release WHERE release_id=$1',
						array ($release_id)) ;
			if (db_numrows($res)<1 ) {
				$FRSRELEASE_OBJ['_'.$release_id.'_']=false;
				return false;
			}
			$data = db_fetch_array($res);
		}
		$FRSPackage = frspackage_get_object($data['package_id']);
		$FRSRELEASE_OBJ['_'.$release_id.'_']= new FRSRelease($FRSPackage,$data['release_id'],$data);
	}
	return $FRSRELEASE_OBJ['_'.$release_id.'_'];
}

class FRSRelease extends FFError {

	/**
	 * Associative array of data from db.
	 *
	 * @var  array   $data_array.
	 */
	var $data_array;

	/**
	 * The FRSPackage.
	 *
	 * @var  object  FRSPackage.
	 */
	var $FRSPackage;
	var $release_files;

	/**
	 * Constructor.
	 *
	 * @param	object  	$FRSPackage	The FRSPackage object to which this release is associated.
	 * @param	int|bool	$release_id	The release_id.
	 * @param	array|bool	$arr		The associative array of data.
	 * @return	bool	success.
	 */
	function __construct(&$FRSPackage, $release_id = false, $arr = false) {
		parent::__construct();
		if (!$FRSPackage || !is_object($FRSPackage)) {
			$this->setError(_('Invalid FRS Package Object'));
			return false;
		}
		if ($FRSPackage->isError()) {
			$this->setError('FRSRelease: '.$FRSPackage->getErrorMessage());
			return false;
		}
		$this->FRSPackage =& $FRSPackage;

		if ($release_id) {
			if (!$arr || !is_array($arr)) {
				if (!$this->fetchData($release_id)) {
					return false;
				}
			} else {
				$this->data_array =& $arr;
				if ($this->data_array['package_id'] != $this->FRSPackage->getID()) {
					$this->setError('FRSPackage_id in db result does not match FRSPackage Object');
					$this->data_array=null;
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * create - create a new release in the database.
	 *
	 * @param	string	$name		The name of the release.
	 * @param	string	$notes		The release notes for the release.
	 * @param	string	$changes	The change log for the release.
	 * @param	int	$preformatted	Whether the notes/log are preformatted with \n chars (1) true (0) false.
	 * @param	int	$release_date	The unix date of the release.
	 * @param	int	$status_id	Active/Hidden status of the release.
	 * @return	boolean	success.
	 */
	function create($name, $notes, $changes, $preformatted, $release_date=false, 
		$status_id=1, $release_desc='', $emailChange=1) {
		if (strlen($name) < 3) {
			$this->setError(_('FRSPackage Name Must Be At Least 3 Characters'));
			return false;
		}

		if ($preformatted) {
			$preformatted = 1;
		} else {
			$preformatted = 0;
		}

		if (!forge_check_perm ('frs', $this->FRSPackage->Group->getID(), 'write')) {
			$this->setPermissionDeniedError();
			return false;
		}

		if (!$release_date) {
			$release_date=time();
		}
		$res = db_query_params ('SELECT * FROM frs_release WHERE package_id=$1 AND name=$2',
					array ($this->FRSPackage->getID(),
						   htmlspecialchars($name))) ;
		if (db_numrows($res)) {
			$this->setError(_('Error Adding Release: Name Already Exists'));
			return false;
		}

		db_begin();
		$result = db_query_params('INSERT INTO frs_release (' .
			'package_id, notes, changes, preformatted, name, ' .
			'release_date, released_by, status_id, simtk_description) VALUES ' .
			'($1,$2,$3,$4,$5,$6,$7,$8,$9)',
			array(
				$this->FRSPackage->getID(),
				htmlspecialchars($notes),
				htmlspecialchars($changes),
				$preformatted,
				htmlspecialchars($name),
				$release_date,
				user_getid(),
				$status_id,
				htmlspecialchars($release_desc)
			)
		);
		if (!$result) {
			$this->setError(_('Error Adding Release: ').db_error());
			db_rollback();
			return false;
		}
		$this->release_id=db_insertid($result,'frs_release','release_id');
		if (!$this->fetchData($this->release_id)) {
			db_rollback();
			return false;
		} else {
			$newdirlocation = forge_get_config('upload_dir').'/'.$this->FRSPackage->Group->getUnixName().'/'.$this->FRSPackage->getFileName().'/'.$this->getFileName();
			if (!is_dir($newdirlocation)) {
				@mkdir($newdirlocation);
			}
			db_commit();
		}

		// Release created.
		if ($emailChange == 1) {
			// Send notice to users monitoring this package.
			$this->sendNotice();
		}

		return true;
	}

	/**
	 * fetchData - re-fetch the data for this Release from the database.
	 *
	 * @param	int	$release_id	The release_id.
	 * @return	bool	success.
	 */
	function fetchData($release_id) {
		$res = db_query_params ('SELECT * FROM frs_release WHERE release_id=$1 AND package_id=$2',
					array ($release_id,
						   $this->FRSPackage->getID())) ;
		if (!$res || db_numrows($res) < 1) {
			$this->setError(_('Invalid release_id'));
			return false;
		}
		$this->data_array = db_fetch_array($res);
		db_free_result($res);
		return true;
	}

	/**
	 * getFRSPackage - get the FRSPackage object this release is associated with.
	 *
	 * @return	object	The FRSPackage object.
	 */
	function &getFRSPackage() {
		return $this->FRSPackage;
	}

	/**
	 * getID - get this release_id.
	 *
	 * @return	int	The id of this release.
	 */
	function getID() {
		return $this->data_array['release_id'];
	}

	/**
	 * getName - get the name of this release.
	 *
	 * @return	string	The name of this release.
	 */
	function getName() {
		return $this->data_array['name'];
	}

	/**
	 * getFileName - get the filename of this release.
	 *
	 * @return	string	The filename of this release.
	 */
	function getFileName() {
		return util_secure_filename($this->data_array['name']);
	}

	/**
	 * getStatus - get the status of this release.
	 *
	 * @return	int	The status.
	 */
	function getStatus() {
		return $this->data_array['status_id'];
	}

	/**
	 * getDesc - get the description of this release.
	 *
	 * @return	int	The description.
	 */
	function getDesc() {
		return $this->data_array['simtk_description'];
	}

	/**
	 * getNotes - get the release notes of this release.
	 *
	 * @return	string	The release notes.
	 */
	function getNotes() {
		return $this->data_array['notes'];
	}

	/**
	 * getChanges - get the changelog of this release.
	 *
	 * @return	string	The changelog.
	 */
	function getChanges() {
		return $this->data_array['changes'];
	}

	/**
	 * getPreformatted - get the preformatted option of this release.
	 *
	 * @return	boolean	preserve_formatting.
	 */
	function getPreformatted() {
		return $this->data_array['preformatted'];
	}

	/**
	 * getReleaseDate - get the releasedate of this release.
	 *
	 * @return	int	The release date in unix time.
	 */
	function getReleaseDate() {
		return $this->data_array['release_date'];
	}

	/**
	 * sendNotice - the logic to send an email notice for a release.
	 *
	 * @return	boolean	success.
	 */
	function sendNotice($action=false) {
		$arr =& $this->FRSPackage->getMonitorIDs();

		$date = date('Y-m-d H:i',time());

		$subject = sprintf (_('[%1$s Release] %2$s'),
					$this->FRSPackage->Group->getUnixName(),
					$this->FRSPackage->getName());

		$formatStr = 'Project %1$s (%2$s) has released "%4$s" in package "%3$s".';
		if ($action == 'UPDATE_RELEASE') {
			$formatStr = 'Project %1$s (%2$s) has updated release "%4$s" in package "%3$s".';
		}
		else if ($action == 'DELETE_RELEASE') {
			$formatStr = 'Project %1$s (%2$s) has deleted release "%4$s" in package "%3$s".';
		}else if ($action == 'DELETE_FILE_RELEASE'){
			$formatStr = 'Project %1$s (%2$s) has deleted a file from release "%4s" in package "%3s".';
		}
		$content = sprintf($formatStr,
			$this->FRSPackage->Group->getPublicName(),
			$this->FRSPackage->Group->getUnixName(),
			$this->FRSPackage->getName(),
			$this->getName());

		$text = $content . "\n\n";
		if (trim($this->getNotes()) != "") {
			$text .= "Release Notes:\n" . $this->getNotes() . "\n\n";
		}
		if (trim($this->getChanges()) != "") {
			$text .= "Change Log:\n" . $this->getChanges() . "\n\n";
		}
		// Action does not begin with DELETE_ we will exclude download link
		$isDeleteAction = strpos($action, "DELETE_");
		if($isDeleteAction === false ){
			$text .= "You can download it by following this link:\n" . 
			util_make_url("/frs/?group_id=". 
				$this->FRSPackage->Group->getID() .
				"&release_id=". $this->getID()) . 
			"\n\n";
		}
		 
		
		$text .= 'You received this email because you requested to be notified when ';

		if($isDeleteAction === false){
			$text .= 'new versions of this package were released. ';
		}else{
			$text .= 'changes were made to this package. ';
		}


		
		$text .= sprintf(_('If you don\'t wish to be notified in the future, '
		. 'please login to %s and click this link:'),forge_get_config('forge_name')) 
		. "\n"
		. util_make_url("/frs/monitor.php?filemodule_id=".
		$this->FRSPackage->getID() . "&group_id=" .
		$this->FRSPackage->Group->getID() . "&stop=1");

		if (count($arr)) {
			util_handle_message(array_unique($arr),$subject,$text);
		}
	}

	/**
	 * newFRSFile - generates a FRSFile (allows overloading by subclasses)
	 *
	 * @param	string	FRS file identifier
	 * @param	array	fetched data from the DB
	 * @return	FRSFile	new FRSFile object.
	 */
	protected function newFRSFile($file_id, $data) {
		return new FRSFile($this,$file_id, $data);
	}

	/**
	 * getFiles - gets all the file objects for files in this release.
	 *
	 * @return	array	Array of FRSFile Objects.
	 */
	function &getFiles() {
		if (!is_array($this->release_files) || count($this->release_files) < 1) {
			$this->release_files=array();
/*
			$res = db_query_params ('SELECT * FROM frs_file_vw WHERE release_id=$1',
						array ($this->getID())) ;
*/
			// Need Simtk 1.0 data here. See fetchData() in FRSFile.class.php.
			$res = db_query_params ('SELECT * FROM frs_file_vw as ffv ' .
				'JOIN (SELECT file_id, simtk_description, ' .
				'simtk_collect_data, simtk_use_mail_list, simtk_group_list_id, ' .
				'simtk_filetype, simtk_filelocation, ' .
				'simtk_show_notes, simtk_show_agreement, ' .
				'simtk_rank, simtk_filename_header, doi, doi_identifier, file_user_id, refresh_archive  ' .
				'FROM frs_file) AS ff ' .
				'ON ffv.file_id=ff.file_id ' .
				'WHERE release_id=$1',
				array ($this->getID())) ;
			while ($arr = db_fetch_array($res)) {
				$this->release_files[]=$this->newFRSFile($arr['file_id'],$arr);
			}
		}
		return $this->release_files;
	}

	/**
	 * delete - delete this release and all its related data.
	 *
	 * @param	bool	$sure		I'm Sure.
	 * @param	bool	$really_sure	I'm REALLY sure.
	 * @return	bool	true/false;
	 */
	function delete($sure, $really_sure, $emailChange=1) {
		if (!$sure || !$really_sure) {
			$this->setMissingParamsError(_('Please tick all checkboxes.'));
			return false;
		}
		if (!forge_check_perm ('frs', $this->FRSPackage->Group->getID(), 'write')) {
			$this->setPermissionDeniedError();
			return false;
		}
		$f =& $this->getFiles();
		for ($i=0; $i<count($f); $i++) {
			if (!is_object($f[$i]) || $f[$i]->isError() || !$f[$i]->delete(true)) {
				$this->setError('File Error: '.$f[$i]->getName().':'.$f[$i]->getErrorMessage());
				return false;
			}
		}
		$dir=forge_get_config('upload_dir').'/'.
			$this->FRSPackage->Group->getUnixName() . '/' .
			$this->FRSPackage->getFileName().'/'.
			$this->getFileName().'/';

		// double-check we're not trying to remove root dir
		if (util_is_root_dir($dir)) {
			$this->setError(_('Release delete error: trying to delete root dir'));
			return false;
		}
		if (is_dir($dir)) {
			rmdir($dir);
		}

		db_query_params ('DELETE FROM frs_release WHERE release_id=$1 AND package_id=$2',
				 array ($this->getID(),
					$this->FRSPackage->getID())) ;

		// This release deleted.
		if ($emailChange == 1) {
			// Send notice to users monitoring this package.
			$this->sendNotice('DELETE_RELEASE');
		}

		return true;
	}

	/**
	 * update - update a new release in the database.
	 *
	 * @param	int	The status of this release from the frs_status table.
	 * @param	string	The name of the release.
	 * @param	string	The release notes for the release.
	 * @param	string	The change log for the release.
	 * @param	int	Whether the notes/log are preformatted with \n chars (1) true (0) false.
	 * @param	int	The unix date of the release.
	 * @param	string	The description of the release.
	 * @return	boolean success.
	 */
	function update($status, $name, $notes, $changes, $preformatted, $release_date, $release_desc, 
		$emailChange=1) {
		if (strlen($name) < 3) {
			$this->setError(_('FRSPackage Name Must Be At Least 3 Characters'));
			return false;
		}

		if (!forge_check_perm('frs', $this->FRSPackage->Group->getID(), 'write')) {
			$this->setPermissionDeniedError();
			return false;
		}

		if ($preformatted) {
			$preformatted = 1;
		} else {
			$preformatted = 0;
		}

		if($this->getName() != htmlspecialchars($name)) {
			$res = db_query_params('SELECT * FROM frs_release WHERE package_id=$1 AND name=$2',
				array(
					$this->FRSPackage->getID(), 
					htmlspecialchars($name)
				)
			);
			if (db_numrows($res)) {
				$this->setError(_('Error On Update: Name Already Exists'));
				return false;
			}
		}
		db_begin();
		$res = db_query_params('UPDATE frs_release SET ' .
			'name=$1, status_id=$2, notes=$3, changes=$4, ' .
			'preformatted=$5, release_date=$6, ' .
			'released_by=$7, simtk_description=$10 ' .
			'WHERE package_id=$8 AND release_id=$9',
			array(
				htmlspecialchars($name),
				$status,
				htmlspecialchars($notes),
				htmlspecialchars($changes),
				$preformatted,
				$release_date,
				user_getid(),
				$this->FRSPackage->getID(),
				$this->getID(),
				htmlspecialchars($release_desc)
			)
		);

		if (!$res || db_affected_rows($res) < 1) {
			$this->setError(sprintf(_('Error On Update: %s'), db_error()));
			db_rollback();
			return false;
		}

		$oldfilename = $this->getFileName();
		if(!$this->fetchData($this->getID())){
			$this->setError(_("Error Updating Release: Couldn't fetch data"));
			db_rollback();
			return false;
		}
		$newfilename = $this->getFileName();
		$olddirlocation = forge_get_config('upload_dir').'/'.$this->FRSPackage->Group->getUnixName().'/'.$this->FRSPackage->getFileName().'/'.$oldfilename;
		$newdirlocation = forge_get_config('upload_dir').'/'.$this->FRSPackage->Group->getUnixName().'/'.$this->FRSPackage->getFileName().'/'.$newfilename;

		if (($oldfilename!=$newfilename) && is_dir($olddirlocation)) {
			if (is_dir($newdirlocation)) {
				$this->setError(_('Error Updating Release: Directory Already Exists'));
				db_rollback();
				return false;
			} else {
				if(!rename($olddirlocation, $newdirlocation)) {
					$this->setError(_("Error Updating Release: Couldn't rename dir"));
					db_rollback();
					return false;
				}
			}
		}
		db_commit();

		// Release updated.
		if ($emailChange == 1) {
			// Send notice to users monitoring this package.
			$this->sendNotice('UPDATE_RELEASE');
		}

		$this->FRSPackage->createNewestReleaseFilesAsZip();
		return true;
	}

	/**
	 * arrangeFiles - Arrange files of a release in the database.
	 *
	 * @param	int	The file id.
	 * @param	int	The rank of the file.
	 * @param	string	The header of the file
	 * @return	boolean success.
	 */
	function arrangeFiles($fileId, $rank, $header) {

		if (!forge_check_perm('frs', $this->FRSPackage->Group->getID(), 'write')) {
			$this->setPermissionDeniedError();
			return false;
		}

		db_begin();

		$res = db_query_params('UPDATE frs_file SET simtk_rank=$1, simtk_filename_header=$2 
			WHERE file_id=$3 AND release_id=$4',
			array(
				$rank, 
				htmlspecialchars($header), 
				$fileId, $this->getID()
			)
		);

		if (!$res || db_affected_rows($res) < 1) {
			$this->setError(sprintf(_('Error On Update: %s'), db_error()));
			db_rollback();
			return false;
		}

		db_commit();

		return true;
	}
	

	// Check if this release or files in this release have DOI assigned.
	function hasDOIIdentifier() {

		// Look at this release.
		$res = db_query_params("SELECT doi_identifier FROM frs_release " .
			"WHERE release_id=$1 " .
			"AND doi_identifier IS NOT NULL ",
			array($this->getId())
		);
		if (!$res || db_numrows($res) > 0) {
			// DOI has been assigned.
			// Do not need to look further.
			return true;
		}

		// Look at files.
		$res = db_query_params("SELECT ff.doi_identifier AS doi_identifier FROM frs_file ff " .
			"JOIN frs_release fr ON ff.release_id=fr.release_id " .
			"WHERE fr.release_id=$1 " .
			"AND ff.doi_identifier IS NOT NULL ",
			array($this->getId())
		);
		if (!$res || db_numrows($res) > 0) {
			// At least 1 of the files has DOI assigned.
			return true;
		}

		return false;
	}

	// Check if this release or files in this release have DOI association.
	function hasDOI() {

		$isDOIPresent = false;

		// Look at this release.
		if ($this->isDOI()) {
			// This release has DOI association.
			// Do not need to look further.
			return true;
		}

		// Look at files.
		$res = db_query_params("SELECT ff.doi AS doi FROM frs_file ff " .
			"JOIN frs_release fr ON ff.release_id=fr.release_id " .
			"WHERE fr.release_id=$1 ",
			array($this->getID())
		);
		if (!$res || db_numrows($res) > 0) {
			while ($arr = db_fetch_array($res)) {
				if (isset($arr["doi"]) && $arr["doi"] == 1) {
					$isDOIPresent = true;
					break;
				}
			}
		}

		return $isDOIPresent;
	}
	
	// Get doi status of release.
	function isDOI() {
		$doi = false;
		$res = db_query_params('SELECT doi FROM frs_release ' .
			'WHERE release_id=$1',
			array($this->getID())
		);
		if (!$res || db_numrows($res) > 0) {
			while ($arr = db_fetch_array($res)) {
				$doi = $arr['doi'];
			}
		}

		return $doi;
	}
	
	function setDoi($user_id, $doi=1) {

		db_begin();

		$res = db_query_params('UPDATE frs_release SET ' .
			'release_user_id=$1, ' .
			'doi=$2 ' .
			'WHERE package_id=$3 ' .
			'AND release_id=$4',
			array(
				$user_id,
				$doi,
				$this->FRSPackage->getID(),
				$this->getID()
			)
		);

		if (!$res || db_affected_rows($res) < 1) {
			$this->setError(sprintf(_('Error On Update: %s'), db_error()));
			db_rollback();
			return false;
		}
        
		db_commit();

		return true;
	}
	
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
