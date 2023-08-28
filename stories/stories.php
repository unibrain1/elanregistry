<?php
$stories = $us_url_root . 'stories/'
?>

<div class="card-header">
    <h2>Car histories and stories</h2>
</div>
<div class="card-body">
    <table class="table table-striped table-bordered table-sm" aria-describedby="legend">
        <colgroup>
            <col span="1" style="width: 50%;">
            <col span="1" style="width: 50%;">
        </colgroup>
        <tr>
            <th scope=column>Article</td>
            <th scope=column>Comments</td>
        </tr>
        <tr>
            <td> <a href="<?= $stories ?>SGO_2F/index.php">The story of SGO 2F: 50/0164</a></td>
            <td></td>
        </tr>

        <tr>
            <td><a href="<?= $stories ?>brian_walton/index.php">Elan Experimental Rally Car: 36/6086</a></td>
            <td></td>
        </tr>

        <tr>
            <td> <a href='<?= $us_url_root ?>docs/embed.php?doc=Mag%20_issue_50_p12-15_Barry-Shapecraft.pdf'>Shapecraft Elan - 26/4992</a>
            </td>
            <td>From <a href="http://www.historiclotusclub.uk/the-magazine/no-50-spring-2022">Historic Lotus Racing magazine, No. 50, Spring 2022</a></td>
        </tr>

        <tr>
            <td> <a href="<?= $stories ?>type26register.php">type26register.com</td>
            <td>An incomplete achive of type26register.com retrieved from the <a href='https://web.archive.org/web/20230000000000*/type26register.com' target="_blank">Wayback Machine</a> This is the site as best as can be recreated from July 2010</td>
        </tr>
    </table>
</div>