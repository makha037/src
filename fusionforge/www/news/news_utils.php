<?php
/**
 * News Facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2002-2004 (c) GForge Team
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2016-2019, Henry Kwong, Tod Hing - SimTK Team
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

function news_header($params) {
	global $HTML, $group_id, $news_name,$news_id;

/*
	if (!forge_get_config('use_news')) {
		exit_disabled();
	}
*/

	$params['toptab']='news';
	$params['group']=$group_id;

	if ($group_id && ($group_id != forge_get_config('news_group'))) {
		$menu_texts=array();
		$menu_links=array();

		$menu_texts[]=_('View News');
		$menu_links[]='/news/?group_id='.$group_id;
		$menu_texts[]=_('Submit');
		$menu_links[]='/news/submit.php?group_id='.$group_id;
		if (session_loggedin()) {
			$project = group_get_object($params['group']);
			if ($project && is_object($project) && !$project->isError()) {
				if (forge_check_perm ('project_admin', $group_id)) {
					$menu_texts[]=_('Administration');
					$menu_links[]='/news/admin/?group_id='.$group_id;
				}
			}
		}
		$params['submenu'] = $HTML->subMenu($menu_texts,$menu_links);
	}
	/*
		Show horizontal links
	*/
	if ($group_id && ($group_id != forge_get_config('news_group'))) {
		site_project_header($params);
	} else {
		site_header($params);
	}
}

function news_footer($params) {
	GLOBAL $HTML;
	$HTML->footer($params);
}

/**
 * Display news for frontpage.
 *
 * @param int  $group_id group_id of the news (forge_get_config('news_group') used if none given)
 * @return string
 */
function news_show_project_overview($group_id=0) {


	if (!$group_id) {
		$group_id=forge_get_config('news_group');
	}

/*
	$result = db_query_params ('
       SELECT news_bytes.summary, news_bytes.post_date, news_bytes.details, news_bytes.forum_id
       FROM news_bytes,groups WHERE (news_bytes.group_id=$1 AND news_bytes.is_approved <> 4)
       AND (news_bytes.is_approved=1)
       AND news_bytes.group_id=groups.group_id
       AND groups.status=$2
       AND news_bytes.simtk_sidebar_display=$3
       ORDER BY post_date DESC',
				   array ($group_id,
					  'A',true),
				   $l);
*/
	$result = db_query_params('SELECT pn.summary, pn.post_date, pn.details, pn.forum_id ' .
		'pn.id ' .
		'FROM plugin_simtk_news pn, ' .
		'JOIN groups g ON pn.group_id=g.group_id ' .
		'WHERE (pn.group_id=$1 ' .
		'AND pn.is_approved <> 4) ' .
		'AND (pn.is_approved=1) ' .
		'AND g.status=$2 ' .
		'AND pn.simtk_sidebar_display=$3 ' .
		'ORDER BY post_date DESC',
		array ($group_id, 'A', true),
		$l);
	$rows=db_numrows($result);

	$return = '';

	if (!$result || $rows < 1) {
		$return .= false;
		$return .= db_error();
	} else {
		$return .= '<div class="news_item">';
		for ($i=0; $i<$rows; $i++) {
			$t_thread_title = db_result($result,$i,'summary');

/*
			$forum_id = db_result($result,$i,'forum_id');
			$return .= "<a href='/forum/forum.php?forum_id=" . $forum_id . "'><h4>". $t_thread_title . '</a></h4>';
*/
			$nid = db_result($result,$i,'id');
			$return .= "<a href='/plugins/simtk_news/news_details.php?' .
				'group_id=" . $group_id . 
				'&id=' . $nid . "'><h4>". $t_thread_title . '</a></h4>';

				//get the first paragraph of the story
                                /*
				if (strstr(db_result($result,$i,'details'),'<br/>')) {
					// the news is html, fckeditor made for example
					$arr=explode("<br/>",db_result($result,$i,'details'));
				} else {
					$arr=explode("\n",db_result($result,$i,'details'));
				}
                                */

                                $return .= '<span class="small grey">' . date("M j, Y" , db_result($result,$i,'post_date')) . '</span>';
				//$summ_txt=util_make_links( $arr[0] );

                                //added from simtk 1.0
                                /*
                                if ($summ_txt) {
                                  $theWordCount = str_word_count($summ_txt);
                                  if ($theWordCount > 25) {
                                        $words = preg_split("/[\s,.]+/", $summ_txt);
                                        $summ_txt = "";
                                        for ($i = 0; $i < 25; $i++) {
                                                $summ_txt .= $words[$i] .' ';
                                        }
                                        $summ_txt .= "...";
                                  }
                                }

				if ($summ_txt != "") {
					$return .= '<p>'.$summ_txt.'</p>';
				}
                                */

                                $re = '/# Split sentences on whitespace between them.
                                        (?<=                # Begin positive lookbehind.
                                          [.!?]             # Either an end of sentence punct,
                                        | [.!?][\'"]        # or end of sentence punct and quote.
                                        )                   # End positive lookbehind.
                                        (?<!                # Begin negative lookbehind.
                                          Mr\.              # Skip either "Mr."
                                        | Mrs\.             # or "Mrs.",
                                        | Ms\.              # or "Ms.",
                                        | Jr\.              # or "Jr.",
                                        | Dr\.              # or "Dr.",
                                        | Prof\.            # or "Prof.",
                                        | Sr\.              # or "Sr.",
                                                                                # or... (you get the idea).
                                        )                   # End negative lookbehind.
                                        \s+                 # Split on whitespace between sentences.
                                        /ix';

                                $arr=preg_split($re,  db_result($result,$i,'details') , -1, PREG_SPLIT_NO_EMPTY);
                                $summ_txt = '';
                                //if the first paragraph is short, and so are following paragraphs, add the next paragraph on
                                if ((strlen($arr[0]) < 50) && (strlen($arr[0].$arr[1]) < 300)) {
                                        if($arr[1])
                                        {
                                                $summ_txt.=$arr[0].'. '.$arr[1];
                                        } else {
                                                $summ_txt.=$arr[0]; // the news has only one sentence
                                        }
                                } else {
                                        $summ_txt.=$arr[0];
                                }

				if ($summ_txt != "") {
					$return .= '<p>'.$summ_txt.'</p>';
				}

                }
		$return .= '</div><!-- class="news_item" -->';

	}
	return $return;
}


/**
 * Display latest news for frontpage or news page.
 *
 * @param int  $group_id group_id of the news (forge_get_config('news_group') used if none given)
 * @param int  $limit number of news to display (default: 10)
 * @param bool $show_summaries (default: true)
 * @param bool $allow_submit (default: true)
 * @param bool $flat (default: false)
 * @param int  $tail_headlines number of additional news to display in short (-1 for all the others, default: 0)
 * @param bool $show_forum
 * @return string
 */
function news_show_latest($group_id=0, $limit=10, $show_summaries=true,
	$allow_submit=true, $flat=false, $tail_headlines=0,
	$show_forum=true, $front_page=false,
	$categoryId="", $suppressDetails=false) {

	if (!$group_id) {
		$group_id=forge_get_config('news_group');
	}
	/*
		Show a simple list of the latest news items with a link to the forum
	*/
	if ($tail_headlines == -1) {
		$l = 0;
	}
	else {
		$l = $limit + $tail_headlines;
	}

	if (isset($categoryId) && $categoryId != "") {
		// NOTE: Retrieve directly related communities from trove_cat_link (one-level deep).
		$strQueryNews = '
			SELECT g.group_name, g.unix_group_name, g.group_id, g.type_id, 
				u.user_name, u.realname, u.picture_file, 
				pn.forum_id, pn.summary, pn.post_date, pn.details, pn.id
			FROM groups g
			JOIN plugin_simtk_news pn ON g.group_id=pn.group_id
			JOIN users u ON u.user_id=pn.submitted_by
			JOIN trove_group_link tgl ON tgl.group_id=pn.group_id
			WHERE (tgl.trove_cat_id=$1 OR
				tgl.trove_cat_id IN (
					SELECT linked_trove_cat_id FROM trove_cat_link
					WHERE trove_cat_id=$1))
				AND (pn.group_id=$2 AND pn.is_approved <> 4 OR 1!=$3)
				AND (pn.is_approved=1 OR 1 != $4)
				AND g.simtk_is_public=1
				AND g.status=$5
			ORDER BY post_date DESC
		';
		$arrQueryNews = array(
			$categoryId,
			$group_id,
			$group_id != forge_get_config('news_group') ? 1 : 0,
			$group_id != forge_get_config('news_group') ? 0 : 1,
			'A'
		);
	}
	else {
		$strQueryNews = '
			SELECT g.group_name, g.unix_group_name, g.group_id, g.type_id, 
				u.user_name, u.realname, u.picture_file,
				pn.forum_id, pn.summary, pn.post_date, pn.details, pn.id
			FROM groups g
			JOIN plugin_simtk_news pn ON g.group_id=pn.group_id
			JOIN users u ON u.user_id=pn.submitted_by
			WHERE (pn.group_id=$1 AND pn.is_approved <> 4 OR 1!=$2)
				AND (pn.is_approved=1 OR 1 != $3)
				AND g.simtk_is_public = 1
				AND g.status=$4
			ORDER BY post_date DESC
		';
		$arrQueryNews = array(
			$group_id,
			$group_id != forge_get_config('news_group') ? 1 : 0,
			$group_id != forge_get_config('news_group') ? 0 : 1,
			'A'
		);
	}

	$result = db_query_params($strQueryNews, $arrQueryNews, $l);
	$rows = db_numrows($result);

	$return = '';

	if (!$result || $rows < 1) {
		$return .= _('No News Found');
		$return .= db_error();
	}
	else {
		for ($i=0; $i<$rows; $i++) {
			$t_thread_title = db_result($result,$i,'summary');
/*
			$t_thread_url = "/forum/forum.php?forum_id=" . db_result($result,$i,'forum_id');
*/
			$t_thread_url = "/plugins/simtk_news/news_details.php?" .
				"group_id=" . $group_id .
				"&id=" . db_result($result,$i,'id');

			$t_thread_author = db_result($result,$i,'realname');

			if ($front_page === true) {
				// Generate the front page news item.
				generate_front_page_news_item($result, $i, $return, $categoryId, $suppressDetails);
				continue;
			}

			$return .= '<div class="one-news bordure-dessous">';
			$return .= "\n";
			if ($show_summaries && $limit) {
				//get the first paragraph of the story
				if (strstr(db_result($result,$i,'details'),'<br/>')) {
					// the news is html, fckeditor made for example
					$arr=explode("<br/>",db_result($result,$i,'details'));
				} else {
					$arr=explode("\n",db_result($result,$i,'details'));
				}
				$summ_txt=util_make_links( $arr[0] );
				$proj_name=util_make_link_g (strtolower(db_result($result,$i,'unix_group_name')),db_result($result,$i,'group_id'),db_result($result,$i,'group_name'));
			} else {
				$proj_name='';
				$summ_txt='';
			}

			if (!$limit) {
				if ($show_forum) {
					$return .= '<h3>'.util_make_link ($t_thread_url, $t_thread_title).'</h3>';
				} else {
					$return .= '<h3>'. $t_thread_title . '</h3>';
				}
				$return .= ' &nbsp; <em>'. date(_('Y-m-d H:i'),db_result($result,$i,'post_date')).'</em><br />';
			}
			else {
				if ($show_forum) {
					$return .= '<h3>'.util_make_link ($t_thread_url, $t_thread_title).'</h3>';
				}
				else {
					$return .= '<h3>'. $t_thread_title . '</h3>';
				}
				$return .= "<div>";
				$return .= '<em>';
				$return .= $t_thread_author;
				$return .= '</em>';
				$return .= ' - ';
				$return .= relative_date(db_result($result,$i,'post_date'));
				$return .= ' - ';
				$return .= $proj_name ;
				$return .= "</div>\n";

				if ($summ_txt != "") {
					$return .= '<p>'.$summ_txt.'</p>';
				}

				$res2 = db_query_params(
					'SELECT total FROM forum_group_list_vw WHERE group_forum_id=$1',
					array(db_result($result,$i,'forum_id')));
				$num_comments = db_result($res2,0,'total');

				if (!$num_comments) {
					$num_comments = '0';
				}

				if ($num_comments <= 1) {
					$comments_txt = _('Comment');
				}
				else {
					$comments_txt = _('Comments');
				}

				if ($show_forum) {
					$link_text = _('Read More/Comment') ;
					$extra_params = array( 'class'      => 'dot-link',
					             		   'title'      => $link_text . ' ' . $t_thread_title);
					$return .= "\n";
					$return .= '<div>' . $num_comments .' '. $comments_txt .' ';
					$return .= util_make_link ($t_thread_url, $link_text, $extra_params);
					$return .= '</div>';
				}
				else {
					$return .= '';
				}
			}

			if ($limit) {
				$limit--;
			}
			$return .= "\n";
			$return .= '</div><!-- class="one-news" -->';
			$return .= "\n\n";
		}

		if ($group_id != forge_get_config('news_group')) {
			$archive_url = '/news/?group_id='.$group_id;
		}
		else {
			$archive_url = '/news/';
		}
		if ($tail_headlines != -1 && $front_page === false) {
			if ($show_forum) {
				$return .= '<div>' . util_make_link($archive_url, _('News archive'), array('class' => 'dot-link')) . '</div>';
			}
			else {
				$return .= '<div>...</div>';
			}
		}
	}
	if ($allow_submit && $group_id != forge_get_config('news_group')) {
		if(!$result || $rows < 1) {
			$return .= '';
		}
		//you can only submit news from a project now
		//you used to be able to submit general news
		$return .= '<div>' . util_make_link ('/news/submit.php?group_id='.$group_id, _('Submit News')).'</div>';
	}
	return $return;
}

function news_foundry_latest($group_id=0,$limit=5,$show_summaries=true) {
	/*
		Show a the latest news for a portal
	*/

/*
	$result=db_query_params("SELECT groups.group_name,groups.unix_group_name,groups.group_id,
		users.user_name,users.realname,news_bytes.forum_id,
		news_bytes.summary,news_bytes.post_date,news_bytes.details
		FROM users,news_bytes,groups,foundry_news
		WHERE foundry_news.foundry_id=$1
		AND users.user_id=news_bytes.submitted_by
		AND foundry_news.news_id=news_bytes.id
		AND news_bytes.group_id=groups.group_id
		AND foundry_news.is_approved=1
		ORDER BY news_bytes.post_date DESC", array($group_id),$limit);
*/
	$result=db_query_params("SELECT g.group_name, g.unix_group_name, g.group_id,
		u.user_name, u.realname,
		pn.forum_id, pn.summary, pn.post_date, pn.details, pn.id
		FROM groups g
		JOIN plugin_simtk_news pn ON g.group_id=pn.group_id
		JOIN users u ON u.user_id=pn.submitted_by
		JOIN foundry_news fn ON fn.news_id=pn.id 
		WHERE fn.foundry_id=$1
		AND fn.is_approved=1
		ORDER BY pn.post_date DESC",
		array($group_id),
		$limit);

	$rows=db_numrows($result);

	if (!$result || $rows < 1) {
		$return .= '<h3>' . _('No News Found') . '</h3>';
		$return .= db_error();
	} else {
		for ($i=0; $i<$rows; $i++) {
			if ($show_summaries) {
				//get the first paragraph of the story
				$arr=explode("\n",db_result($result,$i,'details'));
				if ((isset($arr[1]))&&(isset($arr[2]))&&(strlen($arr[0]) < 200) && (strlen($arr[1].$arr[2]) < 300) && (strlen($arr[2]) > 5)) {
					$summ_txt=util_make_links( $arr[0].'<br />'.$arr[1].'<br />'.$arr[2] );
				} else {
					$summ_txt=util_make_links( $arr[0] );
				}

				//show the project name
				$proj_name=' &nbsp; - &nbsp; '.util_make_link_g (strtolower(db_result($result,$i,'unix_group_name')),db_result($result,$i,'group_id'),db_result($result,$i,'group_name'));
			} else {
				$proj_name='';
				$summ_txt='';
			}
			$return .= util_make_link ('/forum/forum.php?forum_id='. db_result($result,$i,'forum_id'),'<strong>'. db_result($result,$i,'summary') . '</strong>')
				.'<br /><em>'. db_result($result,$i,'realname') .' - '.
					date(_('Y-m-d H:i'),db_result($result,$i,'post_date')) . $proj_name . '</em>
				'. $summ_txt .'';
		}
	}
	return $return;
}

function get_news_name($id) {
	/*
		Takes an ID and returns the corresponding forum name
	*/
/*
	$result=db_query_params('SELECT summary FROM news_bytes WHERE id=$1', array($id));
*/
	$result=db_query_params('SELECT summary FROM plugin_simtk_news WHERE id=$1', array($id));
	if (!$result || db_numrows($result) < 1) {
		return _('Not Found');
	} else {
		return db_result($result, 0, 'summary');
	}
}

// Generate front page news.
function generate_front_page_news_item($result, $i, &$return,
	$categoryId="", $suppressDetails=false) {

	$theFlag = 1;
	if (isset($categoryId) && $categoryId != "") {
		// Has category id; from category page.
		$theFlag = 4 . '&cat=' . $categoryId;
	}

	$t_thread_title = db_result($result, $i, 'summary');

/*
	$t_thread_url = "/forum/forum.php?forum_id=" . db_result($result, $i, 'forum_id');
*/
	$group_id = db_result($result, $i, 'group_id');
	$nid = db_result($result, $i, 'id');
	$t_thread_url = "/plugins/simtk_news/news_details.php?" .
		"group_id=" . $group_id .
		"&id=" . $nid .
		"&flag=$theFlag";

	if (isset($categoryId) && $categoryId != "") {
		$return .= '<div class="item_newsarea">';
	}
	else {
		$return .= '<div class="item_home_news">';
	}

	if ($suppressDetails === false) {
		// Title.
		$return .= '<h4>' . util_make_link($t_thread_url, $t_thread_title) . '</h4>';
		$return .= "\n";
	}

	// Project name.
	$proj_name = util_make_link_g(
		db_result($result, $i, 'unix_group_name'),
		db_result($result, $i, 'group_id'),
		db_result($result, $i, 'group_name'));
	// Date.
	$news_date = date('M j, Y', db_result($result, $i, 'post_date'));
	if ($suppressDetails === false) {
		if (isset($categoryId) && $categoryId != "") {
			$return .= "<div class='newsarea_data'>" . $proj_name . " " . $news_date . "</div>";
		}
		else {
			$return .= "<div class='news_data'>" . $proj_name . " " . $news_date . "</div>";
		}
		$return .= "\n";
	}

	// News item.
	$re = '/# Split sentences on whitespace between them.
                                        (?<=                # Begin positive lookbehind.
                                          [.!?]             # Either an end of sentence punct,
                                        | [.!?][\'"]        # or end of sentence punct and quote.
                                        )                   # End positive lookbehind.
                                        (?<!                # Begin negative lookbehind.
                                          Mr\.              # Skip either "Mr."
                                        | Mrs\.             # or "Mrs.",
                                        | Ms\.              # or "Ms.",
                                        | Jr\.              # or "Jr.",
                                        | Dr\.              # or "Dr.",
                                        | Prof\.            # or "Prof.",
                                        | Sr\.              # or "Sr.",
                                                                                # or... (you get the idea).
                                        )                   # End negative lookbehind.
                                        \s+                 # Split on whitespace between sentences.
                                        /ix';

	$arr = preg_split($re, db_result($result, $i, 'details') , -1, PREG_SPLIT_NO_EMPTY);
	$summ_txt = '';
	// If the first paragraph is short, and so are following paragraphs, 
	// add the next paragraph on.
	if ((strlen($arr[0]) < 50) && isset($arr[1]) && (strlen($arr[0].$arr[1]) < 300)) {
		if ($arr[1]) {
			$summ_txt .= $arr[0] . '. ' . $arr[1];
		}
		else {
			$summ_txt .= $arr[0]; // the news has only one sentence
		}
	}
	else {
		$summ_txt .= $arr[0];
	}

	// User picture file.
	$picture_file = db_result($result, $i, 'picture_file');
	if (trim($picture_file) == "") {
//		$picture_file = "user_default.gif";
		$picture_file = "user_profile.jpg";
	}
	$user_name = db_result($result, $i, 'user_name');
	if ($suppressDetails === false) {
		if ($summ_txt != "") {
			if (isset($categoryId) && $categoryId != "") {
				$return .= '<div class="newsarea_photo">';
			}
			else {
				$return .= '<div class="news_text">';
			}
			$return .= "<a href='/users/" . $user_name . "'>";
			$return .= "<img " .
				' onError="this.onerror=null;this.src=' . "'" . 
				'/userpics/user_profile.jpg' . "';" . '"' .
				' alt="Image not available"' .
				" src='/userpics/" . $picture_file ."' class='news_img'/>";
			$return .= "</a>";
			if (isset($categoryId) && $categoryId != "") {
				$return .= "</div>";
				$return .= '<div class="newsarea_phototext">';
			}
			$return .= html_entity_decode(util_make_clickable_links(util_whitelist_tags($summ_txt)));
			$return .= '</div>';
		}
	}
	else {
		$return .= '<div class="newsarea_photo">';
		$return .= "<a href='/users/" . $user_name . "'>";
		$return .= "<img " .
			' onError="this.onerror=null;this.src=' . "'" . 
			'/userpics/user_profile.jpg' . "';" . '"' .
			' alt="Image not available"' .
			" src='/userpics/" . $picture_file ."' class='news_img'/>";
		$return .= "</a>";
		$return .= "</div>";
		$return .= '<div class="newsarea_phototext">';
		$return .= '<h4 style="margin-top:0px;margin-bottom:0px;">' . util_make_link($t_thread_url, $t_thread_title) . '</h4>';
		$return .= "<div class='newsarea_data'>" . $proj_name . " " . $news_date . "</div>";
		$return .= '</div>';
	}

	$return .= '<div style="clear: both"></div>';
	$return .= '</div>';
	$return .= "\n\n";
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
