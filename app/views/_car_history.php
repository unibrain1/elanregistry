<table id="historytable" style="width: 100%" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
    <thead>
        <tr>
            <th scope=column>Operation</th>
            <th scope=column>Date Modified</th>
            <th scope=column>Year</th>
            <th scope=column>Type</th>
            <th scope=column>Chassis</th>
            <th scope=column>Series</th>
            <th scope=column>Variant</th>
            <th scope=column>Color</th>
            <th scope=column>Engine</th>
            <th scope=column>Purchase Date</th>
            <th scope=column>Sold Date</th>
            <th scope=column>Comments</th>
            <th scope=column>Image</th>
            <th scope=column>Owner</th>
            <th scope=column>City</th>
            <th scope=column>State</th>
            <th scope=column>Country</th>
        </tr>
    </thead>
    <tbody>
        <?php
        //Cycle through users
        foreach ($carHist as $v1) {
        ?>
            <tr>
                <td><?= $v1->operation ?></td>
                <td><?= $v1->timestamp ?></td>
                <td><?= $v1->year ?></td>
                <td><?= $v1->type ?></td>
                <td><?= $v1->chassis ?></td>
                <td><?= $v1->series ?></td>
                <td><?= $v1->variant ?></td>
                <td><?= $v1->color ?></td>

                <td><?= $v1->engine ?></td>
                <td><?= $v1->purchasedate ?></td>
                <td><?= $v1->solddate ?></td>
                <td><?= $v1->comments ?></td>

                <td> <?php
                        if ($v1->image && file_exists($abs_us_root . $us_url_root . "app/userimages/" . $v1->image)) {
                            echo '<img alt="mycar" src=' . $us_url_root . 'app/userimages/thumbs/' . $v1->image . ">";
                        } ?> </td>
                <td><?= $v1->fname ?></td>
                <td><?= $v1->city ?></td>
                <td><?= $v1->state ?></td>
                <td><?= $v1->country ?></td>
            </tr>
        <?php
        } ?>
    </tbody>
</table>