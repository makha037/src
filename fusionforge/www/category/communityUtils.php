<?php

/**
 *
 * Utility file that supports community display.
 * 
 * File to filter projects display.
 *
 * Copyright 2005-2019, SimTK Team
 *
 * This file is part of the SimTK web portal originating from        
 * Simbios, the NIH National Center for Physics-Based               
 * Simulation of Biological Structures at Stanford University,      
 * funded under the NIH Roadmap for Medical Research, grant          
 * U54 GM072970, with continued maintenance and enhancement
 * funded under NIH grants R01 GM107340 & R01 GM104139, and 
 * the U.S. Army Medical Research & Material Command award 
 * W81XWH-15-1-0232R01.
 * 
 * SimTK is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as 
 * published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 * 
 * SimTK is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details. 
 * 
 * You should have received a copy of the GNU General Public 
 * License along with SimTK. If not, see  
 * <http://www.gnu.org/licenses/>.
 */ 
 
// Get administrators.
function getAdministrators($cat_id) {

	$arrAdmins = array();

	$sqlAdmins = 'SELECT user_id FROM trove_admin ' .
		'WHERE trove_cat_id=$1';
	$resAdmins = db_query_params($sqlAdmins, array($cat_id));
	$numAdmins = db_numrows($resAdmins);
	for ($cnt = 0; $cnt < $numAdmins; $cnt++) {
		$arrAdmins[] = db_result($resAdmins, $cnt, 'user_id');
	}

	return $arrAdmins;
}

?>
