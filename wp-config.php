<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'IVd@{2?_u/e(+`Nk,PDhbOJr6wOBmelTG#27edHir@,(z%Vg053F22,faBxXJ1.i' );
define( 'SECURE_AUTH_KEY',   'jWO&k}!8K/i]jt=dRNO$@7(-H,ao4PLl{EN5#P9hSLv;_U?LB5QcqfE$AFs Vn<.' );
define( 'LOGGED_IN_KEY',     'RqJL<F>3a>eM8on?bhy; 3K[/%^hJw>)E@q*j$SK(}V6u>6Jay3<^,?WyM;G4hHR' );
define( 'NONCE_KEY',         'Z!_q(:[K[%?w*Gb7@ol:7JG`tVb?dDF0}B7%~m_Mg@5-Vpk:Ru2=2vu=&7Dikfxr' );
define( 'AUTH_SALT',         'xkzPfpGy>z!6,yxCaPU-)&y@SF9z&D&Nk$66m2P)<D0:JC^!W@7M3EHV1|YrWt(J' );
define( 'SECURE_AUTH_SALT',  ' dHoiL!S~q.FDn=R_+y6ja>74kXxLtmgq2k8RN^z&C)j]<{<a(u6K@BBDopX#M1Q' );
define( 'LOGGED_IN_SALT',    'n;F)$lUN_|FFCzF9R8rrl8 x^Bt)D8Wu8[co?O~1[1MSBHZ]}$<!4n zkdO&z;X@' );
define( 'NONCE_SALT',        ']Ad7G0b?mu]qJkOSzL3/oS_<X.&cq5]Np$(57jF<%`8b9~h)eVs%{c,1agGPno e' );
define( 'WP_CACHE_KEY_SALT', '$@H_4ZY~$(<%S9RxMTuL%ckZSDE((X[P,je[zX2Q7]1%Rdrp_^5H0I::7*9oZbn3' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
