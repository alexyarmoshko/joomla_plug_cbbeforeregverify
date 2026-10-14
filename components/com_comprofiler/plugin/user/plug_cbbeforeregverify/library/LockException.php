<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

namespace CB\Plugin\BeforeRegVerify;

\defined( 'CBLIB' ) or die();

/**
 * Thrown by CBBeforeRegVerify::withLocks() when a lock can't be taken in time, or the
 * database connection was lost or replaced while the locks were held.
 */
class LockException extends \RuntimeException
{
}
