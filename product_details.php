<?php
require_once 'config.php';
require_once 'payment_config.php';

$page_title = 'Product Details';
$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: index.php');
    exit();
}

// Get product details with seller info
$stmt = $pdo->prepare("
    SELECT p.*, u.name as seller_name, u.email as seller_email, u.phone as seller_phone 
    FROM products p 
    JOIN users u ON p.seller_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit();
}

// Check if product is approved or if user is the seller/admin
$can_view = $product['status'] === 'approved' || 
            (isLoggedIn() && $_SESSION['user_id'] == $product['seller_id']) ||
            (isLoggedIn() && getUserRole() === 'admin');

if (!$can_view) {
    header('Location: index.php');
    exit();
}

// Check if current user can buy this product
$can_buy = isLoggedIn() && 
           getUserRole() === 'buyer' && 
           $_SESSION['user_id'] != $product['seller_id'] &&
           $product['status'] === 'approved';


// Check if buyer has already purchased this product
$already_purchased = false;
if ($can_buy) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE buyer_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $already_purchased = $stmt->fetchColumn() > 0;
}

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-0">
                    <?php if ($product['image'] && file_exists('assets/img/' . $product['image'])): ?>
                        <img src="assets/img/<?php echo htmlspecialchars($product['image']); ?>" 
                             class="card-img-top" style="height: 400px; object-fit: cover;" 
                             alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center bg-light" 
                             style="height: 400px;">
                            <i class="fas fa-image fa-5x text-muted"></i>
                        </div>
                    <?php endif; ?>

<!-- Chat Modal -->
<?php if ($can_buy && !$already_purchased): ?>
<div class="modal fade chat-modal" id="chatModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="chat-header-title">
          <span class="presence-dot" id="chatPresence"></span>
          <strong>Negotiation Chat</strong>
          <small class="text-muted ms-2">for: <?php echo htmlspecialchars($product['title']); ?></small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="chatMessages" class="chat-messages"></div>
        <div class="typing-indicator" id="typingIndicator" style="display:none;">Seller is typing…</div>
      </div>
      <div class="modal-footer">
        <div class="input-group">
          <textarea class="form-control chat-input" id="chatInput" rows="1" placeholder="Type your message… (Enter to send, Shift+Enter for newline)"></textarea>
          <button class="btn btn-send" id="sendMsgBtn"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
  </div>
  
</div>
<?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h2 class="me-2 mb-0"><?php echo htmlspecialchars($product['title']); ?></h2>
                        <button id="copyProductLink" class="btn btn-outline-secondary btn-sm"><i class="fas fa-link"></i> Copy Link</button>
                        <span class="badge bg-<?php 
                            echo $product['status'] === 'pending' ? 'warning' : 
                                ($product['status'] === 'approved' ? 'success' : 'danger'); 
                        ?> fs-6">
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <h3 class="text-success"><?php echo formatPrice($product['price']); ?></h3>
                    </div>
                    
                    <?php if ($product['category']): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category']); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Seller Information</h5>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($product['seller_name']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($product['seller_email']); ?>
                                        </small>
                                        <?php if ($product['seller_phone']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($product['seller_phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> Listed on <?php echo date('F j, Y', strtotime($product['created_at'])); ?>
                        </small>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <?php if ($can_buy && !$already_purchased): ?>
                            <button id="openChatBtn" class="btn btn-primary" 
                                    data-product-id="<?php echo (int)$product['id']; ?>"
                                    data-seller-id="<?php echo (int)$product['seller_id']; ?>"
                                    data-buyer-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
                                    data-user-name="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                                <i class="fas fa-comments"></i> Negotiate with Seller
                            </button>
                            <?php if (defined('RAZORPAY_ENABLED') && RAZORPAY_ENABLED): ?>
                                <button id="rzpPayBtn" class="btn btn-success btn-lg">
                                    <i class="fas fa-credit-card"></i> Pay with Razorpay - <?php echo formatPrice($product['price']); ?>
                                </button>
                                <small class="text-muted text-center">
                                    <i class="fas fa-shield-alt"></i> Secure payment via Razorpay. Funds held until delivery.
                                </small>
                            <?php else: ?>
                                <a href="buy_product.php?id=<?php echo $product['id']; ?>" class="btn btn-success btn-lg">
                                    <i class="fas fa-shopping-cart"></i> Proceed to Buy - <?php echo formatPrice($product['price']); ?>
                                </a>
                                <small class="text-muted text-center"><i class="fas fa-shield-alt"></i> Escrow protection</small>
                            <?php endif; ?>
                        <?php elseif ($already_purchased): ?>
                            <button class="btn btn-info btn-lg" disabled>
                                <i class="fas fa-check-circle"></i> Already Purchased
                            </button>
                            <small class="text-muted text-center">
                                You have already purchased this product
                            </small>
                        <?php elseif (isLoggedIn() && $_SESSION['user_id'] == $product['seller_id']): ?>
                            <div class="row">
                                <div class="col-6">
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-warning w-100">
                                        <i class="fas fa-edit"></i> Edit Product
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="delete_product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-danger w-100">
                                        <i class="fas fa-trash"></i> Delete Product
                                    </a>
                                </div>
                            </div>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login to Buy
                            </a>
                        <?php elseif (getUserRole() === 'seller'): ?>
                            <button class="btn btn-secondary btn-lg" disabled>
                                <i class="fas fa-info-circle"></i> Sellers Cannot Buy
                            </button>
                            <small class="text-muted text-center">
                                Switch to a buyer account to purchase products
                            </small>
                        <?php elseif ($product['status'] !== 'approved'): ?>
                            <button class="btn btn-warning btn-lg" disabled>
                                <i class="fas fa-clock"></i> Product Not Available
                            </button>
                            <small class="text-muted text-center">
                                This product is not currently available for purchase
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="text-center">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
                <?php if (isLoggedIn() && getUserRole() === 'buyer'): ?>
                    <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-tachometer-alt"></i> My Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Escrow Information -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h5><i class="fas fa-shield-alt text-success"></i> Escrow Protection</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-lock fa-2x text-primary mb-2"></i>
                                <h6>Secure Payment</h6>
                                <small class="text-muted">Your payment is held safely in escrow</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-handshake fa-2x text-success mb-2"></i>
                                <h6>Delivery Confirmation</h6>
                                <small class="text-muted">Confirm receipt before payment is released</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-undo fa-2x text-warning mb-2"></i>
                                <h6>Refund Protection</h6>
                                <small class="text-muted">Get refund if item is not as described</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php if ($can_buy && defined('RAZORPAY_ENABLED') && RAZORPAY_ENABLED): ?>
<!-- Razorpay Checkout -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
  const btn = document.getElementById('rzpPayBtn');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    try {
      const form = new FormData();
      form.append('product_id', '<?php echo (int)$product['id']; ?>');
      const res = await fetch('api/razorpay_create_order.php', { method: 'POST', body: form });
      const data = await res.json();
      if (!res.ok) { alert(data.error || 'Failed to create order'); return; }
      const options = {
        key: data.key_id,
        amount: data.amount,
        currency: data.currency,
        name: 'Retrade',
        description: data.product.title,
        image: data.product.image ? 'assets/img/' + data.product.image : undefined,
        order_id: data.order_id,
        prefill: { name: data.buyer.name, email: data.buyer.email },
        theme: { color: '#3f51b5' },
        handler: async function (response) {
          try {
            const verifyForm = new FormData();
            verifyForm.append('product_id', data.product.id);
            verifyForm.append('razorpay_order_id', response.razorpay_order_id);
            verifyForm.append('razorpay_payment_id', response.razorpay_payment_id);
            verifyForm.append('razorpay_signature', response.razorpay_signature);
            const vres = await fetch('api/razorpay_verify.php', { method: 'POST', body: verifyForm });
            const vjson = await vres.json();
            if (!vres.ok) { alert(vjson.error || 'Verification failed'); return; }
            window.location.href = 'dashboard.php?success=purchase_completed&transaction_id=' + encodeURIComponent(vjson.transaction_id);
          } catch (e) {
            alert('Verification error');
          }
        }
      };
      const rzp = new Razorpay(options);
      rzp.open();
    } catch (e) {
      alert('Unable to initiate payment');
    }
  });
})();
</script>
<?php endif; ?>
