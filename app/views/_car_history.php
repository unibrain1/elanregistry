               <table id="historytable" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>Operation</th>
                        <th>Date Modified</th>
                        <th>Year</th>
                        <th>Type</th>
                        <th>Chassis</th>
                        <th>Series</th>
                        <th>Variant</th>
                        <th>Color</th>

                        <th>Engine</th>
                        <th>Purchase Date</th>
                        <th>Sold Date</th>
                        <th>Comments</th>

                        <th>Image</th>
                        <th>Owner</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Country</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    //Cycle through users
                    foreach ($carHist as $v1) {
                        ?>
                        <tr>
                        <td><?=$v1->operation?></td>
                        <td><?=$v1->timestamp?></td> 
                        <td><?=$v1->year?></td>
                        <td><?=$v1->type?></td>
                        <td><?=$v1->chassis?></td>
                        <td><?=$v1->series?></td>
                        <td><?=$v1->variant?></td>
                        <td><?=$v1->color?></td>

                        <td><?=$v1->engine?></td>                        
                        <td><?=$v1->purchasedate?></td>
                        <td><?=$v1->solddate?></td>
                        <td><?=$v1->comments?></td>

                        <td> <?php
                        if ($v1->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$v1->image)) {
                            echo '<img src='.$us_url_root.'app/userimages/thumbs/'.$v1->image.">";
                        } ?>  </td>
                        <td><?=$v1->fname?></td>
                        <td><?=$v1->city?></td>
                        <td><?=$v1->state?></td>
                        <td><?=$v1->country?></td> 
                        </tr>
                    <?php
                    } ?>
                    </tbody>
                </table>