<?php
/**
 * Custom Settings Widget Extensions for ElanRegistry
 * 
 * This file leverages UserSpice v5.9.4's custom widget override system
 * to add ElanRegistry-specific menu items to the Settings widget.
 * 
 * These customizations will survive future UserSpice updates.
 */

// Add ElanRegistry-specific menu items using the new v5.9 override system
$tools[] = ['Custom Settings', 'general.png', '?view=custom', 'custom'];
$tools[] = ['Classic Menu', 'navigation.png', '?view=nav', 'nav'];
?>