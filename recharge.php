<?php
/**
 * RISE - Recharge Wallet
 * Full Version: Supports UPI, GPay, PhonePe, Cards, Net Banking, Wallets
 */

$pageTitle = 'Recharge Wallet';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requireAdmin();

if (isSuperAdmin()) {
    setFlashMessage('error', 'Not available for super admin.');
    header('Location: dashboard.php');
    exit;
}

$currentUser = getCurrentUser();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-wallet me-2"></i>Recharge Wallet
                </h5>
            </div>

            <div class="card-body p-4">

                <!-- Amount Input -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        Enter Recharge Amount (₹)
                    </label>

                    <input type="number"
                           class="form-control form-control-lg text-center"
                           id="rechargeAmount"
                           min="100"
                           step="1"
                           placeholder="Enter amount above ₹100"
                           required>

                    <small class="text-muted">
                        Minimum Recharge: ₹100
                    </small>
                </div>

                <!-- Quick Buttons -->
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="100">₹100</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="500">₹500</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="1000">₹1000</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="2000">₹2000</button>
                    <button type="button" class="btn btn-outline-primary quick-amount" data-amount="5000">₹5000</button>
                </div>

                <!-- Pay Button -->
                <button type="button" id="payNowBtn" class="btn btn-success btn-lg w-100">
                    <i class="fas fa-bolt me-2"></i>Pay Now
                </button>

                <!-- Payment Methods Display -->
                <div class="mt-4 border rounded p-3 bg-light text-center">
                    <h6 class="fw-bold text-success mb-3">
                        <i class="fas fa-shield-alt me-1"></i>Accepted Payment Methods
                    </h6>

                    <div class="row text-center">
                        <div class="col-4 mb-3">
                            <i class="fas fa-mobile-alt fs-3"></i>
                            <p class="small mb-0">UPI</p>
                        </div>
                        <div class="col-4 mb-3">
                            <i class="fab fa-google-pay fs-3"></i>
                            <p class="small mb-0">Google Pay</p>
                        </div>
                        <div class="col-4 mb-3">
                            <i class="fas fa-mobile fs-3"></i>
                            <p class="small mb-0">PhonePe</p>
                        </div>

                        <div class="col-4 mb-3">
                            <i class="fas fa-university fs-3"></i>
                            <p class="small mb-0">Net Banking</p>
                        </div>
                        <div class="col-4 mb-3">
                            <i class="fas fa-credit-card fs-3"></i>
                            <p class="small mb-0">Debit/Credit Card</p>
                        </div>
                        <div class="col-4 mb-3">
                            <i class="fas fa-wallet fs-3"></i>
                            <p class="small mb-0">Wallets</p>
                        </div>
                    </div>

                    <small class="text-muted">
                        Powered by Razorpay Secure Gateway
                    </small>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Razorpay Checkout -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Quick amount buttons
    document.querySelectorAll('.quick-amount').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('rechargeAmount').value = this.dataset.amount;
        });
    });

    // Pay button click
    document.getElementById('payNowBtn').addEventListener('click', function () {

        const amount = parseInt(document.getElementById('rechargeAmount').value);

        if (!amount || amount < 100) {
            alert('Minimum recharge amount is ₹100');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Order...';

        // Create Order
        const formData = new FormData();
        formData.append('amount', amount);
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

        fetch('create_order.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(orderData => {

            if (!orderData.success) {
                alert(orderData.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt me-2"></i>Pay Now';
                return;
            }

            var options = {
                key: '<?php echo RAZORPAY_KEY_ID; ?>',
                amount: orderData.amount,
                currency: "INR",
                name: "<?php echo APP_NAME; ?>",
                description: "Wallet Recharge",
                order_id: orderData.order_id,

                handler: function (response) {

                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

                    const verifyData = new FormData();
                    verifyData.append('razorpay_payment_id', response.razorpay_payment_id);
                    verifyData.append('razorpay_order_id', response.razorpay_order_id);
                    verifyData.append('razorpay_signature', response.razorpay_signature);
                    verifyData.append('amount', amount);
                    verifyData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');

                    fetch('razorpay_handler.php', {
                        method: 'POST',
                        body: verifyData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Recharge successful! ₹' + amount + ' added.');
                            window.location.href = 'wallet.php';
                        } else {
                            alert('❌ ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-bolt me-2"></i>Pay Now';
                        }
                    });
                },

                prefill: {
                    name: "<?php echo addslashes($currentUser['name']); ?>",
                    email: "<?php echo addslashes($currentUser['email']); ?>"
                },

                theme: {
                    color: "#198754"
                },

                modal: {
                    ondismiss: function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-bolt me-2"></i>Pay Now';
                    }
                }
            };

            var rzp = new Razorpay(options);
            rzp.open();
        })
        .catch(err => {
            alert('Error creating payment order.');
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-2"></i>Pay Now';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>