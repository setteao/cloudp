<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Nov 2017
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _InstallLanguage
{
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		/* Get count...this is an estimate but is good enough for the progress bar */
		$data['originalCount'] = \IPS\Db::i()->select( 'COUNT(DISTINCT(word_key))', 'core_sys_lang_words', array( 'word_app=?', $data['application'] ) )->first();

		if ( !$data['originalCount'] )
		{
			return NULL;
		}

		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$offset = intval( $offset );

		if( \IPS\Application::appIsEnabled( $data['application'] ) AND file_exists( \IPS\ROOT_PATH . "/applications/{$data['application']}/data/lang.xml" ) )
		{
			$rowsInserted = \IPS\Application::load( $data['application'] )->installLanguages( $offset, 500 );

			/* If we didn't insert any, then we're done */
			if( $rowsInserted )
			{
				return $rowsInserted + $offset;
			}
		}

		throw new \IPS\Task\Queue\OutOfRangeException;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'backgroundQueue_importing_english', FALSE, array( 'sprintf' => \IPS\Application::load( $data['application'] )->_title ) ), 'complete' => $data['originalCount'] ? ( round( 100 / $data['originalCount'] * $offset, 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data
	 * @return	void
	 */
	public function postComplete( $data )
	{
		$data = json_decode( $data['data'], TRUE );

		\IPS\Db::i()->query( "REPLACE INTO " . \IPS\Db::i()->prefix . "core_sys_lang_words (lang_id, word_app, word_plugin, word_theme, word_key, word_custom, word_default, word_default_version, word_custom_version, word_js, word_export ) SELECT {$data['language_id']} AS lang_id, word_app, word_plugin, word_theme, word_key, word_custom, word_default, word_default_version, word_custom_version, word_js, 0 FROM " . \IPS\Db::i()->prefix  . "core_sys_lang_words WHERE word_export=0 AND word_app='{$data['application']}' AND lang_id=" . \IPS\Lang::defaultLanguage() );

		unset( \IPS\Data\Store::i()->languages );
	}
}