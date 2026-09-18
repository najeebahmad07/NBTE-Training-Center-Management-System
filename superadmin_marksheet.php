<?php
/**
 * Super Admin Dashboard
 */

$pageTitle = 'Dashboard';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requireLogin();

if (!isSuperAdmin()) {
    http_response_code(403);
    die('Access Denied');
}
?>

<!-- Dashboard Content -->

<div class="row mb-4">

    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h6 class="mb-0">
                    <i class="fas fa-file-pdf me-2"></i>
                    View Original Marksheet
                </h6>

            </div>


            <div class="card-body">

                <div class="row justify-content-center">

                    <div class="col-md-6 col-lg-5">

                        <div class="text-center">

                            <h5 class="mb-2">
                                View Your Result
                            </h5>

                            <p class="text-muted mb-4">
                                Enter the student's Roll Number
                                to view the original marksheet.
                            </p>

                        </div>


                        <!-- Existing Form Logic -->

                        <form
                            method="GET"
                            action="superadmin_marksheet_search.php"
                        >

                            <div class="mb-3">

                                <label
                                    for="enrollment"
                                    class="form-label"
                                >
                                    Roll Number
                                </label>

                                <input
                                    type="text"
                                    name="enrollment"
                                    id="enrollment"
                                    class="form-control"
                                    placeholder="Enter Roll Number"
                                    required
                                    autocomplete="off"
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >

                                <i class="fas fa-eye me-2"></i>

                                View Marksheet

                            </button>

                        </form>


                        <div class="text-center mt-3">

                            <small class="text-muted">

                                This is for viewing the original
                                marksheet only.

                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once 'includes/footer.php'; ?>