<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\BeforeRegVerify\Table;

use CBLib\Database\Table\Table;
use CBLib\Language\CBTxt;

\defined( 'CBLIB' ) or die();

class VerificationTable extends Table
{
	public const STATUS_SENT	=	'sent';
	public const STATUS_RESENT	=	'resent';
	public const STATUS_ATTEMPT	=	'attempt';

	public const OUTCOME_PENDING	=	'pending';
	public const OUTCOME_VERIFIED	=	'verified';
	public const OUTCOME_CANCELLED	=	'cancelled';
	public const OUTCOME_FAILED		=	'failed';

	/** @var int */
	public $id;
	/** @var string */
	public $email;
	/** @var null|string */
	public $code_hash;
	/** @var string */
	public $status;
	/** @var string */
	public $outcome;
	/** @var int */
	public $ttl;
	/** @var string */
	public $sent_at;
	/** @var string */
	public $modified_at;
	/** @var null|string */
	public $request_ip;
	/** @var null|string */
	public $note;

	/**
	 * Table name in database
	 *
	 * @var string
	 */
	protected $_tbl		=	'#__comprofiler_plugin_beforeregverify';

	/**
	 * Primary key in table
	 *
	 * @var string
	 */
	protected $_tbl_key	=	'id';

	/**
	 * @return bool
	 */
	public function check(): bool
	{
		if ( $this->getString( 'email', '' ) === '' ) {
			$this->setError( CBTxt::T( 'CBBEFOREREGVERIFY_EMAIL_REQUIRED', 'Email is required.' ) );

			return false;
		}

		if ( $this->getString( 'status', '' ) === '' ) {
			$this->setError( CBTxt::T( 'CBBEFOREREGVERIFY_STATUS_REQUIRED', 'Status is required.' ) );

			return false;
		}

		if ( $this->getString( 'outcome', '' ) === '' ) {
			$this->setError( CBTxt::T( 'CBBEFOREREGVERIFY_OUTCOME_REQUIRED', 'Outcome is required.' ) );

			return false;
		}

		if ( ! in_array( $this->getString( 'status', '' ), [ self::STATUS_SENT, self::STATUS_RESENT, self::STATUS_ATTEMPT ], true ) ) {
			$this->setError( CBTxt::T( 'CBBEFOREREGVERIFY_STATUS_INVALID', 'Status is invalid.' ) );

			return false;
		}

		if ( ! in_array( $this->getString( 'outcome', '' ), [ self::OUTCOME_PENDING, self::OUTCOME_VERIFIED, self::OUTCOME_CANCELLED, self::OUTCOME_FAILED ], true ) ) {
			$this->setError( CBTxt::T( 'CBBEFOREREGVERIFY_OUTCOME_INVALID', 'Outcome is invalid.' ) );

			return false;
		}

		return true;
	}

	/**
	 * @param string $email
	 * @return self|null
	 */
	public static function loadActiveByEmail( string $email ): ?self
	{
		global $_CB_database;

		$query	=	'SELECT ' . $_CB_database->NameQuote( 'id' )
				.	"\n FROM " . $_CB_database->NameQuote( '#__comprofiler_plugin_beforeregverify' )
				.	"\n WHERE " . $_CB_database->NameQuote( 'email' ) . " = " . $_CB_database->Quote( $email )
				.	"\n AND " . $_CB_database->NameQuote( 'outcome' ) . " = " . $_CB_database->Quote( self::OUTCOME_PENDING )
				.	"\n AND " . $_CB_database->NameQuote( 'status' ) . " IN ( " . $_CB_database->Quote( self::STATUS_SENT ) . ', ' . $_CB_database->Quote( self::STATUS_RESENT ) . ' )'
				.	"\n ORDER BY " . $_CB_database->NameQuote( 'id' ) . ' DESC';
		$_CB_database->setQuery( $query, 0, 1 );
		$id		=	(int) $_CB_database->loadResult();

		if ( ! $id ) {
			return null;
		}

		$row	=	new self();
		$row->load( $id );

		if ( ! $row->getInt( 'id', 0 ) ) {
			return null;
		}

		return $row;
	}
}
