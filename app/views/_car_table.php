<!-- Year -->
                        <div class="form-group row">
                            <label for="year" class="col-2 col-form-label">Year</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-calendar-check"></i><!-- Change to something that makes sense -->
                                    </div>
                                    <select class="custom-select" name="year" onchange="populateSub(this, this.form.model);">
                                        <option selected><?= $cardetails['year'] ?></option>
                                        <option>1963</option>
                                        <option>1964</option>
                                        <option>1965</option>
                                        <option>1966</option>
                                        <option>1967</option>
                                        <option>1968</option>
                                        <option>1969</option>
                                        <option>1970</option>
                                        <option>1971</option>
                                        <option>1972</option>
                                        <option>1973</option>
                                        <option>1974</option>
                                    </select>
                                </div> 
                            <span id="yearHelpBlock" class="form-text text-muted">Select Year</span>
                            </div>
                        </div>

                        <!-- Model -->
                        <div class="form-group row">
                            <label for="model" class="col-2 col-form-label">Model</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-car-side"></i>
                                        
                                    </div>
                                    <select class="custom-select" name="model">
                                        <option selected><?= $cardetails['model'] ?></option>
                                        <option value="">--Please Choose--</option>
                                    </select>
                                </div> 
                            <span id="modelHelpBlock" class="form-text text-muted">Select Model</span>
                            </div>
                        </div>

                        <!-- Chassis -->
                        <div class="form-group row">
                            <label for="chassis" class="col-2 col-form-label">Chassis</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-barcode"></i>
                                    </div>
                                    <input class='form-control' type='text' name='chassis' placeholder='<?= $carprompt['chassis'] ?>' value='<?= $cardetails['chassis'] ?>' /> <!-- Add some validation -->
                                </div> 
                            <span id="chassisHelpBlock" class="form-text text-muted">Enter Chassis Number - Pre 1970 - xxxx, 1970 and on 70xxyy0001z</span>
                            </div>
                        </div>

                        <!-- Color -->
                        <div class="form-group row">
                            <label for="color" class="col-2 col-form-label">Color</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-palette"></i>
                                    </div>
                                    <input class='form-control' type='text' name='color' placeholder='<?= $carprompt['color'] ?>' value='<?= $cardetails['color'] ?>' />
                                </div> 
                            <span id="colorHelpBlock" class="form-text text-muted">Enter Color</span>
                            </div>
                        </div>

                        <!-- Engine Number -->
                        <div class="form-group row">
                            <label for="engine" class="col-2 col-form-label">Engine Number</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-car"></i> 
                                    </div>
                                    <input class='form-control' type='text' name='engine' placeholder='<?= $carprompt['engine'] ?>' value='<?= $cardetails['engine'] ?>' />  <!-- Add validation -->
                                </div> 
                            <span id="engineHelpBlock" class="form-text text-muted">Enter Engine Number</span>
                            </div>
                        </div>

                        <!-- Purchase Date  -->
                        <div class="form-group row">
                            <label for="purchasedate" class="col-2 col-form-label">Purchase Date </label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-calendar"></i> <!-- Change to something that makes sense -->
                                    </div>
                                    <input class="form-control" id="purchasedate" name="purchasedate" placeholder='<?= $carprompt['purchasedate'] ?>' type="text"/> 
                                </div> 
                            <span id="purchaseHelpBlock" class="form-text text-muted">Enter Purchase Date</span>
                            </div>
                        </div>

                        <!-- Sold Date -->
                        <div class="form-group row">
                            <label for="solddate" class="col-2 col-form-label">Sold Date</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-calendar"></i> <!-- Change to something that makes sense -->
                                    </div>
                                    <input class="form-control" id="solddate" name="solddate" placeholder='<?= $carprompt['solddate'] ?>' type="text"/>  
                                </div> 
                            <span id="soldHelpBlock" class="form-text text-muted">Enter Date Sold</span>
                            </div>
                        </div>                     


                        <!-- Comments -->
                        <div class="form-group row">
                            <label for="comment" class="col-2 col-form-label">Comments</label> 
                            <div class="col-10">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-comment-alt"></i>
                                    </div>
                                    <textarea class="form-control" name='comments' rows='10' wrap='virtual' placeholder='<?= $carprompt['comments'] ?>'><?= htmlspecialchars($cardetails['comments']); ?></textarea>
                                </div> 
                            <span id="commentHelpBlock" class="form-text text-muted">Please give a brief history of your car and anything special</span>
                            </div>
                        </div> 