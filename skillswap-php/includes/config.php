<?php
/* =============================================================
   SkillSwap NSU  —  config.php
   -------------------------------------------------------------
   The only file you may need to edit. The values below are the
   XAMPP defaults, so on a stock XAMPP install nothing has to
   change: start Apache and MySQL, import the SQL, done.
   ============================================================= */

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_USER = 'root';
const DB_PASS = '';            // XAMPP's MySQL has no root password by default
const DB_NAME = 'skillexchange';

/* Shown in the top bar so nobody mistakes the demo for production. */
const APP_NAME = 'SkillSwap NSU';

/* Every seeded student shares this password. Registration writes a
   fresh hash, so this only applies to the fifty seeded accounts. */
const DEMO_PASSWORD = 'password123';

/* Uncomment while developing to see PHP errors in the page. */
// ini_set('display_errors', '1'); error_reporting(E_ALL);
