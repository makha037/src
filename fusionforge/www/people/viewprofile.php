<?php
/**
 * Skills viewer page.
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2002 (c) Silicon and Software Systems (S3)
 * Copyright 2010-2013, Franck Villaume - TrivialDev
 * http://fusionforge.org/
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

require_once '../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'people/people_utils.php';
require_once $gfwww.'people/skills_utils.php';

global $HTML;

if (!forge_get_config('use_people')) {
	exit_disabled('home');
}

$group_id = getIntFromRequest('group_id');
$job_id = getIntFromRequest('job_id');

$user_id = getIntFromRequest('user_id');
if ($user_id && is_numeric($user_id)) {

	/*
		Fill in the info to create a job
	*/
	//for security, include group_id
	$result = db_query_params('SELECT * FROM users WHERE user_id=$1', array($user_id));
	if (!$result || db_numrows($result) < 1) {
		$error_msg .= _('No Such User')._(': ').db_error();
		people_header(array('title' => _('View a User Profile')));
	} else {
		people_header(array('title' => _('View a User Profile')));
		/*
			profile set private
		*/
		$overwritten_access = 0;
		if (session_loggedin()) {
			$u = user_get_object(user_getid());
			if ($u->getID() == $user_id) {
				$overwritten_access = 1;
			}
		}
		if ((db_result($result, 0, 'people_view_skills') != 1) && !$overwritten_access) {
			echo $HTML->warning_msg(_('This user has set his/her profile to private.'));
			people_footer();
			exit;
		}
		echo '<p>
			<strong>'._('Skills profile for').' : </strong>'. db_result($result, 0, 'realname') .
			' ('.db_result($result, 0, 'user_name') .
			')</p>';
		if ($overwritten_access) {
			echo util_make_link('/people/editprofile.php', _('Edit your profile'));
		}
		echo '<table class="fullwidth">';
		displayUserSkills($user_id, 0);
		echo '</table>';
	}

	people_footer();

} else {
	/*
		Not logged in or insufficient privileges
	*/
	exit_error(_('User_id not found.'),'home');
}
