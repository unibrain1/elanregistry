<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
	die();
}

?>


<div id="page-wrapper">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<h3>Identification Guide</h3>
				This is not a comprehensive list of differences between the cars
				rather a quick guide to identifying a car. Like all manufacturers,
				there were running changes throughout the life of the Elan. In the
				case of Lotus, many of these changes were poorly documented. If
				in doubt, post a question to the
				<a href="http://www.lotuselan.net/forums/">LotusElan.net forum</a> and ask.


				<ul>
					<li><a href="#roadster">Roadster</a></li>
					<li><a href="#coupe">Coupe</a></li>
					<li><a href="#race">Race</a></li>
					<li><a href="#plus2">Plus 2</a></li>
					<li><a href='<?= $us_url_root ?>app/assets/docs/embed.php?doc=2019_Jan_The_Elan_Super_Safety.pdf'>Super Safety</a></li>

				</ul>

				<a id=" roadster"></a>
				<fieldset>
					<legend>Roadster/Drophead</legend>
					<table class="table table-striped table-bordered table-sm" aria-describedby="legend">
						<tr>
							<th scope=column style="width: 20%">Model</th>
							<th scope=column style="width: 50%">Description </th>
							<th scope=column style="width: 30%">Image</th>
						</tr>
						<tr>
							<td><strong>Elan 1500</strong><br><em> Type 26 Elan 1500 Roadster </em> </td>
							<td>
								<ul>
									<li>1500 badge on boot</li>
									<li>All were recalled from the factory and updated to Elan 1600 specification</li>
									<li>Boot lid does not extend all the way to the trailing edge of the car</li>
									<li>Lift up windows</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Elan 1600 </strong><br><em> Type 26 S1 Roadster </em>
							</td>
							<td>
								<ul>
									<li>1600 badge on boot</li>
									<li>Round tail lights</li>
									<li>No rollup windows</li>
									<li>Boot lid does not extend all the way to the trailing edge of the car</li>
									<li>Lift up windows</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/elan1600.jpg" height="112" width="150" title="Type 26 Elan 1600 (S1)" alt="Type 26 Elan 1600 (S1)"></td>
						</tr>

						<tr>
							<td><strong>Roadster S2 </strong><br><em> Type 26 S2 Roadster </em>
							</td>
							<td>
								<ul>
									<li>No rollup windows</li>
									<li>Oval tail lights. Early cars had round lights</li>
									<li>Boot lid does not extend all the way to the trailing edge of the car</li>
									<li>Lift up windows</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s2a.jpg" height="112" width="150" title="Type 26 Elan S2" alt="Type 26 Elan S2">
								< <img class="polaroid" src="images/examples/thumbs/s2b.jpg" height="112" width="150" title="Type 26 Elan S2" alt="Type 26 Elan S2">
							</td>
						</tr>

						<tr>
							<td><strong>Drophead S3 </strong><br><em> Type 45 S3 DHC </em>
							</td>
							<td>
								<ul>
									<li>Electric rollup windows with fixed frames</li>
									<li>Oval tail lights</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Drophead S3 S/E </strong><br><em> Type 45 S3 S/E DHC </em>
							</td>
							<td>
								<ul>
									<li>Electric rollup windows with fixed frames</li>
									<li>Oval tail lights</li>
									<li>S/E badged</li>
									<li>Stainless steel side trims & wing mounted flasher repeaters on most cars</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s3_se_dhc.jpg" height="90" width="150" title="Type 45 S3 S/E DHC" alt="Type 45 S3 S/E DHC"></td>
						</tr>

						<tr>
							<td><strong>Drophead S4 </strong><br><em> Type 45 S4 DHC </em>
							</td>
							<td>
								<ul>
									<li>Square tail lights</li>
									<li>Square profile wheelarches</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s4_dhc.jpg" height="89" width="150" title="Type 45 S4 DHC" alt="Type 45 S4 DHC"></td>
						</tr>

						<tr>
							<td><strong>Drophead S4 S/E </strong><br><em> Type 45 S4 S/E DHC </em>
							</td>
							<td>
								<ul>
									<li>Square tail lights</li>
									<li>Square profile wheelarches</li>
									<li>S/E badged</li>
									<li>Stainless steel side trims & wing mounted flasher repeaters on most cars</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s4_se_dhc_b.jpg" height="112" width="150" title="Type 45 S4 S/E DHC" alt="Type 45 S4 S/E DHC">
								<img class="polaroid" src="images/examples/thumbs/s4_se_dhc_a.jpg" height="112" width="150" title="Type 45 S4 S/E DHC" alt="Type 45 S4 S/E DHC">
							</td>
						</tr>

						<tr>
							<td><strong>Drophead Sprint </strong><br><em> Type 45 Sprint DHC </em>
							</td>
							<td>
								<ul>
									<li><a href="http://www.lotuselansprint.com">Complete Details</a></li>
									<li>Square tail lights</li>
									<li>Badged as Sprint</li>
									<li>Unique Two tone paint. Could be deleted as an option</li>
									<li>Square profile wheelarches</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/sprint_dhc.jpg" height="84" width="150" title="Type 45 Sprint DHC" alt="Type 45 Sprint DHC"></td>
						</tr>
					</table>
				</fieldset>

				<a id="coupe"></a>
				<fieldset>
					<legend>Coupe</legend><br>
					<table class="table table-striped table-bordered table-sm" aria-describedby="legend">
						<tr>
						<tr>
							<th scope=column style="width: 20%">Model</th>
							<th scope=column style="width: 50%">Description </th>
							<th scope=column style="width: 30%">Image</th>
						</tr>
						<td><strong>Coupe S3 Pre-Airflow </strong><br><em> Type 36 FHC-preairflow </em>
						</td>
						<td>
							<ul>
								<li>Oval tail lights</li>
								<li>No extractor grill on the B pillar</li>
							</ul>
						</td>
						<td><img class="polaroid" src="images/examples/thumbs/s3_fhc_pre.jpg" height="99" width="150" title="Type 36 FHC preairflow" alt="Type 36 FHC preairflow"></td>
						</tr>

						<tr>
							<td><strong>Coupe S3 Airflow </strong><br><em> Type 36 S3 FHC </em>
							</td>
							<td>
								<ul>
									<li>Oval tail lights</li>
									<li>Extractor grill on the rear quarter panel</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s3_fhc_air.jpg" height="100" width="150" title="Type 36 FHC" alt="Type 36 FHC"></td>
						</tr>

						<tr>
							<td><strong>Coupe S3 S/E Airflow </strong><br><em> Type 36 S3 S/E FHC </em>
							</td>
							<td>
								<ul>
									<li>Oval tail lights</li>
									<li>Extractor grill on the rear quarter panel</li>
									<li>S/E badged</li>
									<li>Stainless steel side trims & wing mounted flasher repeaters on most cars</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Coupe S4 </strong><br><em> Type 36 S4 FHC </em>
							</td>
							<td>
								<ul>
									<li>Square tail lights</li>
									<li>Square profile wheelarches</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/s4_fhc.jpg" height="79" width="150" title="Type 36 S4 FHC" alt="Type 36 S4 FHC"></td>
						</tr>

						<tr>
							<td><strong>Coupe S4 S/E </strong><br><em> Type 36 S4 S/E FHC </em>
							</td>
							<td>
								<ul>
									<li>Square tail lights</li>
									<li>Square profile wheelarches</li>
									<li>S/E badged</li>
									<li>Stainless steel side trims & wing mounted flasher repeaters on most cars</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Coupe Sprint </strong><br><em> Type 36 Sprint FHC </em>
							</td>
							<td>
								<ul>
									<li><a href="http://www.lotuselansprint.com">Complete Details</a></li>
									<li>Square tail lights</li>
									<li>Badged as Sprint</li>
									<li>Unique Two tone paint. Could be deleted as an option</li>
									<li>Square profile wheelarches</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/sprint_fhc.jpg" height="56" width="150" title="Type 45 Sprint DHC" alt="Type 45 Sprint DHC">
							</td>
						</tr>
					</table>
				</fieldset>

				<a id="race"></a>
				<fieldset>
					<legend>Racing Version</legend>
					<table class="table table-striped table-bordered table-sm" aria-describedby="legend">
						<tr>
							<th scope=column style="width: 20%">Model</th>
							<th scope=column style="width: 50%">Description </th>
							<th scope=column style="width: 30%">Image</th>
						</tr>
						<tr>
							<td><strong>26R </strong><br><em> Type 26 26R Race </em>
							</td>
							<td>
								<ul>
									<li>Fixed headlights, Most not all</li>
									<li>Magnesium Lotus designed peg drive wheels</li>
									<li>S2 versions had lightweight bodies and flared fenders</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/26r.jpg" height="83" width="150" title="Type 26R" alt="Type 26R">
							</td>
						</tr>
					</table>
				</fieldset>


				<a id="plus2"></a>
				<fieldset>
					<legend>Plus 2</legend>
					<table class="table table-striped table-bordered table-sm" aria-describedby="legend">
						<tr>
							<th scope=column style="width: 20%">Model</th>
							<th scope=column style="width: 50%">Description </th>
							<th scope=column style="width: 30%">Image</th>
						</tr>
						<tr>
							<td><strong>Plus 2</strong><br><em>Type 50 +2</em>
							</td>
							<td>
								<ul>
									<li>50/0001 on </li>
									<li>2 large gauges - Speedometer, Tachometer</li>
									<li>4 small gauges - Water Temp, Oil Pressure, Ammeter, Fuel</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/plus2int.jpg" height="112" width="150" title="Type 50 Plus 2 Dash" alt="Type 50 Plus 2 Dash">
						</tr>

						<tr>
							<td><strong>Plus 2 Federal</strong><br><em>Type 50 +2 Federal</em>
							</td>
							<td>
								<ul>
									<li>50/0857 on for US, 50/0929 all markets </li>
									<li>2 large gauges - Speedometer, Tachometer</li>
									<li>4 small gauges - Water Temp, Oil Pressure, Ammeter, Fuel</li>
									<li>Remote boot release, flush interior door handles, modified exhaust</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Plus 2S </strong><br><em>Type 50 +2 2S</em>
							</td>
							<td>
								<ul>
									<li>50/1593 on </li>
									<li>2 large gauges - Speedometer, Tachometer</li>
									<li>6 small gauges -Oil, Water temp, Battery Condition, Temp, Fuel, Clock</li>
									<li>4 warning lights in center of dash (Hazard, Parking Brake, Brake Fail, Rear Screen)</li>
									<li>New luxury interior including revised seats and centre console</li>
									<li>Fog/Driving lamps below the bumper</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/plus2sa.jpg" height="99" width="150" title="Type 50 Plus 2S" alt="Type 50 Plus 2S">
								<img class="polaroid" src="images/examples/thumbs/plus2sb.jpg" height="99" width="150" title="Type 50 Plus 2S" alt="Type 50 Plus 2S">
							</td>
						</tr>

						<tr>
							<td><strong>Plus 2S Federal </strong><br><em>Type 50 +2 2S Federal</em>
							</td>
							<td>
								<ul>
									<li>50/2447 on </li>
									<li>2 large gauges - Speedometer, Tachometer</li>
									<li>6 small gauges -Oil, Water temp, Battery Condition, Temp, Fuel, Clock</li>
									<li>4 warning lights in center of dash (Hazard, Parking Brake, Brake Fail, Rear Screen)</li>
									<li>Luxury interior including revised seats and centre console</li>
									<li>Fog/Driving lamps below the bumper</li>
								</ul>
							</td>
							<td></td>
						</tr>

						<tr>
							<td><strong>Plus 2S 130</strong><br><em>Type 50 +2 130</em>
							</td>
							<td>
								<ul>
									<li>71.01...0001 on </li>
									<li>2 large gauges - Speedometer, Tachometer</li>
									<li>6 small gauges - Oil, Water temp, Battery Condition, Temp, Fuel, Clock</li>
									<li>3 warning lights in center of dash (Hazard, Park/Brake Fail, Rear Screen) </li>
									<li>Luxury interior including revised seats and centre console</li>
									<li>Big Valve engine
									<li>Fog/Driving lamps below the bumper</li>
								</ul>
							</td>
							<td><img class="polaroid" src="images/examples/thumbs/plus2s130a.jpg" height="112" width="150" title="Type 50 Plus2S 130" alt="Type 50 Plus2S 130">
								<img class="polaroid" src="images/examples/thumbs/plus2s130b.jpg" height="112" width="150" alt="Type 50 Plus2S 130" title="Type 50 Plus2S 130">
								<img class="polaroid" src="images/examples/thumbs/plus2s130int.jpg" height="112" width="150" alt="Type 50 Plus2S 130" title="Type 50 Plus2S 130">
								<img class="polaroid" src="images/examples/thumbs/plus2s130d.jpg" height="112" width="150" alt="Type 50 Plus2S 130" title="Type 50 Plus2S 130">
							</td>
						</tr>

						<tr>
							<td><strong>Plus 2S 130/5</strong><br><em>Type 50 +2 130/5</em>
							</td>
							<td>
								<ul>
									<li>72.10... on </li>
									<li>Same as Plus 2S 130 with 5-speed gearbox</li>
								</ul>
							</td>
							<td></td>
						</tr>
					</table>
				</fieldset>





				<!-- End of main content section -->
			</div> <!-- /.col -->
		</div> <!-- /.row -->
	</div> <!-- container -->
</div> <!-- page-wrapper -->

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>