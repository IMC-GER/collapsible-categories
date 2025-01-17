<?php
/**
*
* Collapsible Categories extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace phpbb\collapsiblecategories\event;

use phpbb\collapsiblecategories\operator\operator_interface;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var operator_interface */
	protected $operator;

	/** @var template */
	protected $template;

	/**
	 * Constructor
	 *
	 * @param operator_interface $operator Collapsible categories operator object
	 * @param template           $template Template object
	 */
	public function __construct(operator_interface $operator, template $template)
	{
		$this->operator = $operator;
		$this->template = $template;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 */
	public static function getSubscribedEvents()
	{
		return array(
			'core.user_setup'									=> 'load_language_on_setup',
			'core.display_forums_modify_category_template_vars'	=> 'show_collapsible_categories',
			'core.display_forums_modify_template_vars'			=> 'show_collapsible_categories',
			'core.search_modify_param_before'					=> 'modify_search_param',
		);
	}

	/**
	 * Load common language file during user setup
	 *
	 * @param \phpbb\event\data $event The event object
	 *
	 * @return void
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'phpbb/collapsiblecategories',
			'lang_set' => 'collapsiblecategories',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Set category display states
	 *
	 * @param \phpbb\event\data $event The event object
	 *
	 * @return void
	 */
	public function show_collapsible_categories($event)
	{
		$fid = 'fid_' . $event['row']['forum_id'];
		$row = isset($event['cat_row']) ? 'cat_row' : 'forum_row';
		$event_row = $event[$row];
		$event_row = array_merge($event_row, array(
			'S_FORUM_HIDDEN'	=> $this->operator->is_collapsed($fid),
			'U_COLLAPSE_URL'	=> $this->operator->get_collapsible_link($fid),
		));
		$event[$row] = $event_row;
	}

	/**
	 * Get childs from forum
	 *
	 * @param	int		$parent_id			foren id
	 *
	 * @return array	$forum_child_ids	ids form forum childs
	 */
	function get_forum_child_ids($parent_id)
	{
		global $db;
		$forum_child_ids = [];

		$sql = 'SELECT forum_id FROM ' . FORUMS_TABLE . " WHERE parent_id = $parent_id";
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$forum_child_ids[] = $row['forum_id'];
		}

		$db->sql_freeresult($result);

		return $forum_child_ids;
	}

	/**
	 * Modify the SQL parameters before pre-made searches
	 *
	 * @param			$event 		The event object
	 * @var		array	ex_fid_ary	Array of excluded forum ids
	 * @return	void
	 */
	public function modify_search_param($event)
	{
		$ex_fid			 = $event['ex_fid_ary'];
		$collapsible_fid = $this->operator->get_user_categories();

		foreach ($collapsible_fid as &$value)
		{
			$value = substr($value, (strpos($value, '_') + 1));
		}

		for ($i = 0; $i < count($collapsible_fid); $i++)
		{
			$collapsible_fid = array_merge($collapsible_fid, $this->get_forum_child_ids($collapsible_fid[$i]));
		}

		$ex_fid = array_merge($ex_fid, $collapsible_fid);
		$event['ex_fid_ary'] = $ex_fid;
	}
}
