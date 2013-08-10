<?php
/**
*
* @package Unread posts counter
* @copyright (c) 2013 Pico88
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
    exit;
}

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class phpbb_ext_pico88_unreadpostscounter_event_listener implements EventSubscriberInterface
{
	/**
	* Get subscribed events
	*
	* @return array
	* @static
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.index_modify_page_title'					=> 'display_posts_number',
		);
	}

	/**
	* Display unread posts on index
	*
	* @param Event $event Event object
	* @return null
	*/
	public function display_posts_number($event)
	{
		global $phpbb_container, $user;

		$unread_posts_count = $this->count_posts();

		$this->container = $phpbb_container;
		$this->container->get('user')->add_lang_ext('pico88/unreadpostscounter', 'unread_posts_counter');
		$this->container->get('template')->assign_vars(array(
			'L_SEARCH_UNREAD'		=> ($unread_posts_count == 0) ? $user->lang['NO_UNREAD_POSTS'] : sprintf($user->lang['UNREAD_POSTS'], $unread_posts_count),
		));
	}

	/**
	* Let's dirty works and count unread posts
	*
	* @return unread posts number
	*/
	private function count_posts()
	{
		global $auth, $db, $user;

		if (($user->data['user_id'] == ANONYMOUS) || $user->data['is_bot'])
		{
			return 0;
		}

		// Select unread topics
		$ex_fid_ary = array_unique(array_merge(array_keys($auth->acl_getf('!f_read', true)), array_keys($auth->acl_getf('!f_search', true))));

		if ($auth->acl_get('m_approve'))
		{
			$m_approve_fid_ary = array(-1);
			$m_approve_fid_sql = '';
		}
		else if ($auth->acl_getf_global('m_approve'))
		{
			$m_approve_fid_ary = array_diff(array_keys($auth->acl_getf('!m_approve', true)), $ex_fid_ary);
			$m_approve_fid_sql = ' AND (t.topic_approved = 1' . ((sizeof($m_approve_fid_ary)) ? ' OR ' . $db->sql_in_set('t.forum_id', $m_approve_fid_ary, true) : '') . ')';
		}
		else
		{
			$m_approve_fid_ary = array();
			$m_approve_fid_sql = ' AND t.topic_approved = 1';
		}

		$sql_where = 'AND t.topic_moved_id = 0
			' . $m_approve_fid_sql . '
			' . ((sizeof($ex_fid_ary)) ? 'AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '');

		$last_mark = (int) $user->data['user_lastmark'];
		$unread_topics = array();

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (tt.user_id = ' . $user->data['user_id'] . ' AND t.topic_id = tt.topic_id)
			LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . ' AND t.forum_id = ft.forum_id)
			WHERE
				(
					(tt.mark_time IS NOT NULL AND t.topic_last_post_time > tt.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND t.topic_last_post_time > ft.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NULL AND t.topic_last_post_time > ' . $last_mark . ')
				)
				' . $sql_where . '
			LIMIT 1001';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$unread_topics[] = $row['topic_id'];
		}
		$db->sql_freeresult($result);

		if (empty($unread_topics))
		{
			return 0;
		}

		// Count unread posts
		$sql = 'SELECT COUNT(p.post_id) AS count
			FROM ' . POSTS_TABLE . ' p
			LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (p.forum_id = ft.forum_id AND ft.user_id = ' . $user->data['user_id'] . ')
			LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (p.topic_id = tt.topic_id AND tt.user_id = ' . $user->data['user_id'] . ')
			WHERE ' . $db->sql_in_set('p.topic_id', $unread_topics) . ' AND 
				p.post_visibility = 1 AND
				(
					(tt.mark_time IS NOT NULL AND p.post_time > tt.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND p.post_time > ft.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NULL AND p.post_time > ' . $last_mark . ')
				)';
		$result = $db->sql_query($sql);
		$unread_posts = $db->sql_fetchfield('count', false, $result);
		$db->sql_freeresult($result);

		return $unread_posts;
	}
}
