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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'greenorchids' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost:8889' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         '{.#*:ix4Lq`:]^*O8mJu.{#3Um@`ZvADTl.gpGj2Dy+F=f8D.QHY=Hc/6RzNlYJV' );
define( 'SECURE_AUTH_KEY',  'CRTjqX?rJ2vv+k=u(baYA6y&T6<HO#Cs~sro~cR|8blO=bDxV?QUDv1NhW+0SPoB' );
define( 'LOGGED_IN_KEY',    'jh&n/$je GuvKpuq$P=/A<J+ ;P6GZJIH!;JC8)@OC_l|$ 37}IOY8`q$TE> H4u' );
define( 'NONCE_KEY',        '5d`Dt`4|VIi*$MTX|h^m`mo1H0-R>u30~}QdZ*@HrN}- DJ5b4y/*nb$`.F;#VqX' );
define( 'AUTH_SALT',        '^=/xR9(in;h6`-2$xfba)N<o^B:8D{Sgal?XAhnF4EX8;fmz{sbZt,7]0wOOC@i5' );
define( 'SECURE_AUTH_SALT', ' =jorZhYu_zHmm*nA<#>9P(0;YE8|/h@l<gV1(f6`Ngra)76/.V)8hDpCvU%ExnQ' );
define( 'LOGGED_IN_SALT',   'CA%5PvEddaF?^YyN#rF:olDRxIg>:+h$O~u|!4>0/+MkW x/dBM7Lj[bNLbdSBKr' );
define( 'NONCE_SALT',       '#(~Pv.:_+gcj`j{ %,+[-ak(;GNHC|~?fcJ:MzXF&|Cg-uW!<wK/@*;x$9%TW~En' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
