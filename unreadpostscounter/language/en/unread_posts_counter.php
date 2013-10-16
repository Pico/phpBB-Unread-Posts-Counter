<?php
/**
*
* unread_posts_counter [English]
* 
* @package language
* @copyright (c) 2013 Pico88
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'NO_UNREAD_POSTS'			=> 'You have no unread posts',
	'UNREAD_POSTS'				=> 'View unread posts (%d)',
));
