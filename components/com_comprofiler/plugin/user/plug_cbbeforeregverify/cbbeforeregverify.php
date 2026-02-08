<?php
/**
 * CBBeforeRegVerify Plugin
 * @version $Id: $
 * @package CBBeforeRegVerify
 * @copyright (C) 2026 Yak Shaver https://www.kayakshaver.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU/GPL version 2
 */

use CB\Plugin\BeforeRegVerify\CBBeforeRegVerify;
use CB\Plugin\BeforeRegVerify\Trigger\UserTrigger;
use CBLib\Core\AutoLoader;

if ( ! ( defined( '_VALID_CB' ) || defined( '_JEXEC' ) || defined( '_VALID_MOS' ) ) ) { die( 'Direct Access to this location is not allowed.' ); }

global $_PLUGINS;

AutoLoader::registerExactMap( '%^CB/Plugin/BeforeRegVerify/(.+)%i', __DIR__ . '/library/$1.php' );

$_PLUGINS->loadPluginGroup( 'user' );

CBBeforeRegVerify::runMaintenance();

$_PLUGINS->registerFunction( 'onBeforeRegisterFormRequest', 'onBeforeRegisterFormRequest', UserTrigger::class );
$_PLUGINS->registerFunction( 'onBeforeRegisterForm', 'onBeforeRegisterForm', UserTrigger::class );
$_PLUGINS->registerFunction( 'onBeforeRegisterFormDisplay', 'onBeforeRegisterFormDisplay', UserTrigger::class );
$_PLUGINS->registerFunction( 'onAfterRegisterFormDisplay', 'onAfterRegisterFormDisplay', UserTrigger::class );
$_PLUGINS->registerFunction( 'onBeforeSaveUserRegistrationRequest', 'onBeforeSaveUserRegistrationRequest', UserTrigger::class );
$_PLUGINS->registerFunction( 'onBeforeUserRegistration', 'onBeforeUserRegistration', UserTrigger::class );
$_PLUGINS->registerFunction( 'onAfterSaveUserRegistration', 'onAfterSaveUserRegistration', UserTrigger::class );
