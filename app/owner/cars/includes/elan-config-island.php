<?php
declare(strict_types=1);

$elanSizes = explode(',', ELAN_IMAGE_THUMBNAIL_SIZES);
$elanThumbnailSize = intval(trim($elanSizes[0]));
$elanResponsiveSize = intval(trim($elanSizes[1]));
?>
<script nonce="<?= htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
window.ELAN_CONFIG = {
    THUMBNAIL_SIZE: <?= $elanThumbnailSize ?>,
    RESPONSIVE_SIZE: <?= $elanResponsiveSize ?>
};
</script>
