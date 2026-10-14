<?php
/*
Plugin Name: Insights from Google PageSpeed
Plugin URI: http://mattkeys.me
Description: Check every page with Google PageSpeed Insights (Performance, Core Web Vitals, Accessibility, Best Practices, SEO and AI agents) and follow your scores in wp-admin.
Author: Matt Keys
Version: 5.0.0
Author URI: http://mattkeys.me
Requires at least: 6.6
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: google-pagespeed-insights
*/

/*  Copyright 2016  Matt Keys  (email : me@mattkeys.me)
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Path to this file
if ( ! defined( 'GPI_PLUGIN_FILE' ) ) {
	define( 'GPI_PLUGIN_FILE', __FILE__ );
}

// Path to the plugin's directory
if ( ! defined( 'GPI_DIRECTORY' ) ) {
	define( 'GPI_DIRECTORY', dirname( __FILE__ ) );
}

// Publicly accessible path
if ( ! defined( 'GPI_PUBLIC_PATH' ) ) {
	define( 'GPI_PUBLIC_PATH', plugin_dir_url( __FILE__ ) );
}

// Plugin basename
if ( ! defined( 'GPI_BASENAME' ) ) {
	define( 'GPI_BASENAME', plugin_basename( __FILE__ ) );
}

// Internal version number 
if ( ! defined( 'GPI_VERSION' ) ) {
	define( 'GPI_VERSION', '5.0.0' );
}

if ( is_admin() ) {
	require 'classes/class-GPI-Activation.php';
	require 'classes/class-GPI-Uninstall.php';
	require 'classes/class-GPI-Admin.php';
}

require 'classes/class-GPI-Core.php';
require 'classes/class-GPI-Data.php';
require 'classes/class-GPI-REST.php';
