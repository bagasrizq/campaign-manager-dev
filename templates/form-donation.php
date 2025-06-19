<?php
/**
 * Template for donation form
 * 
 * This template displays the donation form for campaigns
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get campaign data
$campaign_id = isset($atts['campaign_id']) ? intval($atts['campaign_id']) : get_the_ID();
$campaign = get_post($campaign_id);

if (!$campaign || $campaign->post_type !== 'campaign') {
    return '<div class="error">Campaign tidak ditemukan.</div>';
}

// Get campaign meta
$target = get_post_meta($campaign_id, '_campaign_target', true);
$deadline = get_post_meta($campaign_id, '_campaign_deadline', true);
$status = get_post_meta($campaign_id, '_campaign_status', true) ?: 'active';
$currency = get_post_meta($campaign_id, '_campaign_currency', true) ?: 'IDR';
$currency_symbol = $currency === 'IDR' ? 'Rp' : '$';

// Check if campaign is active
$is_expired = $deadline && strtotime($deadline) < time();
$is_active = $status === 'active' && !$is_expired;

if (!$is_active) {
    return '<div class="donation-form-inactive">Campaign ini sudah tidak aktif.</div>';
}

// Generate nonce for security
$nonce = wp_create_nonce('campaign_donation_form');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Donasi - <?php echo esc_html($campaign->post_title); ?></title>
    <style>
        /* ===== Base Styles ===== */
        body {
            font-family: sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 1rem;
        }

        /* ===== Container ===== */
        .donation__container,
        .payment-method__container,
        .user-info__container {
            max-width: 48rem;
            margin: 2.5rem auto;
            padding: 0.625rem 2rem;
            background-color: #ffffff;
            border-radius: 0.625rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.12);
            box-sizing: border-box;
        }

        /* ===== Banner / Gambar ===== */
        .donation__intro img {
            width: 100%;
            height: auto;
            border-radius: 0.375rem;
            display: block;
            margin-bottom: 1.5rem;
        }

        .campaign-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .campaign-info .label {
            font-weight: 600;
            color: #666;
        }

        .campaign-info .value {
            font-weight: bold;
            color: #2c3e50;
        }

        /* ===== Heading & Note ===== */
        .donation__note,
        .payment-method__note,
        .user-info__note {
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            color: #555;
        }

        .donation__heading,
        .payment-method__heading,
        .user-info__heading {
            font-size: 1.125rem;
            font-weight: bold;
            margin-bottom: 1.25rem;
        }

        /* ===== Bank Transfer Info ===== */
        .bank-transfer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: transparent;
            border: 1px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .bank-transfer-info:hover {
            border-color: #13723b;
            background-color: #f8f9fa;
        }

        .bank-transfer-info.selected {
            border-color: #13723b;
            background-color: #f0f8f4;
        }

        .bank-transfer-info .bank-logo {
            flex-shrink: 0;
        }

        .bank-transfer-info .bank-text {
            line-height: 1.4;
            font-size: 0.9rem;
        }

        .selectable {
            cursor: pointer;
        }

        /* ===== Opsi Nominal ===== */
        .donation__options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .donation__button {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            font-size: 1rem;
        }

        .donation__button:hover {
            border-color: #13723b;
            background-color: #f8f9fa;
        }

        .donation__button.selected {
            background-color: transparent;
            color: #13723b;
            border-color: #13723b;
        }

        /* ===== Form Fields ===== */
        .form__group {
            margin-bottom: 1.25rem;
        }

        .form__label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }

        .form__required {
            color: red;
        }

        .form__desc {
            font-size: 0.8125rem;
            font-style: italic;
            color: #666;
            margin-top: 0.5rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 0.75rem 0.875rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            border-color: #13723b;
            outline: none;
            box-shadow: 0 0 0 0.125rem rgba(19, 114, 59, 0.1);
        }

        /* ===== Form Check ===== */
        .form__check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .form__check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .form__check label {
            cursor: pointer;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .form__check label a {
            color: #13723b;
            text-decoration: none;
        }

        .form__check label a:hover {
            text-decoration: underline;
        }

        /* ===== Error Messages ===== */
        .form-messages {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }

        .form-messages.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .form-messages.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .donation-form-inactive,
        .error {
            text-align: center;
            padding: 30px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px auto;
            max-width: 48rem;
        }

        /* ===== Sticky Donation Bar ===== */
        .donation-sticky {
            position: sticky;
            bottom: 0;
            width: 100%;
            background-color: #ffffff;
            border-radius: 0.625rem 0 0.625rem 0;
            box-shadow: -0.5rem -0.5rem 1.5rem rgba(0, 0, 0, 0.12);
            padding: 1rem 0;
            z-index: 100;
            max-width: 48rem;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .donation-sticky__content {
            max-width: 48rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-sizing: border-box;
        }

        .donation-sticky__label {
            font-size: 0.875rem;
            color: #555;
        }

        .donation-sticky__amount {
            font-size: 1.5rem;
            font-weight: 1000;
            color: #13723b;
        }

        .donation-sticky__button {
            background-color: #13723b;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
            flex-grow: 1;
            margin-left: 2rem;
            text-align: center;
            position: relative;
        }

        .donation-sticky__button:hover {
            background-color: #0e522a;
        }

        .donation-sticky__button:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }

        .btn-loading {
            display: none;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== Responsive: Tablet & Mobile ===== */
        @media (max-width: 768px) {
            .donation__container,
            .payment-method__container,
            .user-info__container {
                margin: 1rem auto;
                padding: 0.5rem 1rem;
            }

            .donation__options {
                grid-template-columns: 1fr;
            }

            .campaign-info {
                flex-direction: column;
                gap: 10px;
            }

            .donation-sticky__content {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                padding: 0 1.875rem;
                gap: 0.75rem;
            }

            .donation-sticky__summary {
                flex: 1 1 50%;
                min-width: 9.375rem;
            }

            .donation-sticky__button {
                flex: 1 1 45%;
                min-width: 7.5rem;
                margin-left: 0;
            }

            .donation-sticky__amount {
                font-size: 1.25rem;
            }

            .donation-sticky__label {
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
        }

        @media (max-width: 480px) {
            .donation-sticky__content {
                gap: 0.5rem;
            }

            .donation-sticky__summary,
            .donation-sticky__button {
                flex: 1 1 48%;
            }

            .donation-sticky__amount {
                font-size: 1.125rem;
                text-align: left;
            }

            .donation-sticky__label {
                text-align: left;
            }
        }
    </style>
</head>
<body>

<form id="campaign-donation-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
    <!-- Hidden Fields -->
    <input type="hidden" name="action" value="process_campaign_donation">
    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
    <input type="hidden" name="donation_nonce" value="<?php echo $nonce; ?>">

    <!-- Section: Nominal Donasi -->
    <section class="donation">
        <div class="donation__container">
            <div class="donation__intro">
                <?php if (has_post_thumbnail($campaign_id)): ?>
                    <p><?php echo get_the_post_thumbnail($campaign_id, 'large'); ?></p>
                <?php else: ?>
                    <p><img src="https://placehold.co/600x200/f0f0f0/333333?text=<?php echo urlencode($campaign->post_title); ?>" alt="Banner Campaign" /></p>
                <?php endif; ?>

                <!-- Campaign Info -->
                <?php if ($target || $deadline): ?>
                <div class="campaign-info">
                    <?php if ($target): ?>
                        <div class="target-info">
                            <span class="label">Target:</span>
                            <span class="value"><?php echo $currency_symbol; ?> <?php echo number_format($target, 0, ',', '.'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($deadline): ?>
                        <div class="deadline-info">
                            <span class="label">Deadline:</span>
                            <span class="value"><?php echo date('d F Y', strtotime($deadline)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <p class="donation__note">Kamu ingin donasi berapa hari ini?</p>
                <h3 class="donation__heading">Nominal Donasi</h3>
                <label for="donation-other" class="form__label">Pilih Nominal</label>

                <div class="donation__options">
                    <button type="button" class="donation__button" data-value="50000">Rp. 50.000</button>
                    <button type="button" class="donation__button" data-value="100000">Rp. 100.000</button>
                    <button type="button" class="donation__button" data-value="250000">Rp. 250.000</button>
                    <button type="button" class="donation__button" data-value="500000">Rp. 500.000</button>
                    <button type="button" class="donation__button" data-value="1000000">Rp. 1.000.000</button>
                    <button type="button" class="donation__button" data-value="custom">Nominal Lain</button>
                </div>

                <div class="form__group">
                    <label for="donation-other" class="form__label">Nominal Lain</label>
                    <input type="number" id="donation-other" name="amount" placeholder="Minimum Transfer - Rp.10.000" min="10000" step="1000" required />
                    <p class="form__desc">Minimal transaksi untuk metode pembayaran QRIS <?php echo $currency_symbol; ?> 10.000</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section: Metode Pembayaran -->
    <section class="payment-method">
        <div class="payment-method__container">
            <p class="payment-method__note">Pilih Metode Pembayaran</p>
            <h3 class="payment-method__heading">Metode Pembayaran</h3>

            <label class="bank-transfer-info selectable">
                <input type="radio" name="payment_method" value="manual" hidden checked />
                <div class="bank-logo">
                    <img src="https://fokusbinaquran.org/wp-content/uploads/2025/05/bsi-logo24.svg" width="40" height="40" alt="Logo BSI">
                </div>
                <div class="bank-text">
                    <strong>Bank Syariah Indonesia</strong><br>
                    <span>Transfer Manual</span>
                </div>
            </label>

            <label class="bank-transfer-info selectable">
                <input type="radio" name="payment_method" value="ewallet" hidden />
                <div class="bank-logo">
                    <img src="https://fokusbinaquran.org/wp-content/uploads/2025/05/quick-response-code-indonesia-standard-qris-seeklogo.svg" width="40" height="40" alt="Logo QRIS">
                </div>
                <div class="bank-text">
                    <strong>QRIS</strong><br>
                    <span>E-Wallet & Mobile Banking</span>
                </div>
            </label>

            <label class="bank-transfer-info selectable">
                <input type="radio" name="payment_method" value="bank_transfer" hidden />
                <div class="bank-logo">
                    <span style="font-size: 24px;">🏦</span>
                </div>
                <div class="bank-text">
                    <strong>Virtual Account</strong><br>
                    <span>VA BCA, Mandiri, BNI, BRI</span>
                </div>
            </label>
        </div>
    </section>

    <!-- Section: Informasi Pribadi -->
    <section class="user-info">
        <div class="user-info__container">
            <p class="user-info__note">Isi data-data di bawah untuk informasi akses di website ini.</p>
            <h3 class="user-info__heading">Informasi Pribadi</h3>

            <div class="form__group">
                <label for="user-name" class="form__label">Nama <span class="form__required">*</span></label>
                <input type="text" id="user-name" name="nama" placeholder="Masukkan nama anda" required />
            </div>

            <div class="form__group">
                <label for="user-email" class="form__label">Alamat Email <span class="form__required">*</span></label>
                <input type="email" id="user-email" name="email" placeholder="Masukkan alamat email" required />
                <p class="form__desc">Kami akan mengirimkan konfirmasi pembayaran ke alamat email ini</p>
            </div>

            <div class="form__group">
                <label for="user-phone" class="form__label">No WhatsApp <span class="form__required">*</span></label>
                <p class="form__desc">Kami akan menggunakan no WhatsApp untuk mengirim konfirmasi pembayaran transaksi anda</p>
                <input type="tel" id="user-phone" name="no_hp" placeholder="Masukkan no handphone" pattern="[0-9]{10,13}" required />
            </div>

            <div class="form__group">
                <label for="user-address" class="form__label">Alamat (Opsional)</label>
                <textarea id="user-address" name="alamat" rows="2" placeholder="Masukkan alamat lengkap"></textarea>
            </div>

            <div class="form__group">
                <label for="donation-notes" class="form__label">Pesan Dukungan (Opsional)</label>
                <textarea id="donation-notes" name="notes" rows="3" placeholder="Tuliskan pesan dukungan Anda untuk campaign ini..."></textarea>
            </div>

            <div class="form__check">
                <input type="checkbox" id="anonymous-donation" name="anonymous" value="1">
                <label for="anonymous-donation">Sembunyikan nama saya (donasi anonim)</label>
            </div>

            <div class="form__check">
                <input type="checkbox" id="subscribe-newsletter" name="subscribe_newsletter" value="1">
                <label for="subscribe-newsletter">Saya ingin menerima update campaign melalui email</label>
            </div>

            <div class="form__check">
                <input type="checkbox" id="agree-terms" name="agree_terms" value="1" required>
                <label for="agree-terms">Saya setuju dengan <a href="#" target="_blank">syarat dan ketentuan</a> donasi</label>
            </div>

            <!-- Form Messages -->
            <div id="form-messages" class="form-messages"></div>
        </div>
    </section>
</form>

<!-- Sticky Total Donasi -->
<div class="donation-sticky">
    <div class="donation-sticky__content">
        <div class="donation-sticky__summary">
            <div class="donation-sticky__label">Anda Akan Berdonasi</div>
            <div class="donation-sticky__amount">Rp 0</div>
        </div>
        <button type="submit" form="campaign-donation-form" class="donation-sticky__button" id="submit-donation">
            <span class="btn-text">Transfer Donasi</span>
            <span class="btn-loading" style="display: none;">
                <span class="spinner"></span> Memproses...
            </span>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('campaign-donation-form');
    const donationButtons = document.querySelectorAll('.donation__button');
    const donationOtherInput = document.getElementById('donation-other');
    const donationAmountDisplay = document.querySelector('.donation-sticky__amount');
    const submitButton = document.getElementById('submit-donation');
    const messagesDiv = document.getElementById('form-messages');
    const paymentOptions = document.querySelectorAll('.bank-transfer-info');
    const phoneInput = document.getElementById('user-phone');
    const emailInput = document.getElementById('user-email');

    // Format Rupiah function
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(angka);
    }

    function updateDonationAmount(amount) {
        donationAmountDisplay.textContent = formatRupiah(amount);
    }

    // Handle donation button clicks
    donationButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove selected from all buttons
            donationButtons.forEach(btn => btn.classList.remove('selected'));
            
            // Add selected to clicked button
            button.classList.add('selected');
            
            const value = button.dataset.value;
            if (value !== 'custom') {
                donationOtherInput.value = value;
                updateDonationAmount(parseInt(value, 10));
            } else {
                donationOtherInput.focus();
            }
        });
    });

    // Handle custom amount input
    donationOtherInput.addEventListener('input', function() {
        donationButtons.forEach(btn => btn.classList.remove('selected'));
        
        const customBtn = document.querySelector('[data-value="custom"]');
        if (this.value) {
            customBtn.classList.add('selected');
        }
        
        const value = parseInt(this.value, 10) || 0;
        updateDonationAmount(value);
    });

    donationOtherInput.addEventListener('focus', function() {
        donationButtons.forEach(btn => btn.classList.remove('selected'));
        const customBtn = document.querySelector('[data-value="custom"]');
        customBtn.classList.add('selected');
    });

    // Handle payment method selection
    paymentOptions.forEach(option => {
        option.addEventListener('click', function() {
            paymentOptions.forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            option.querySelector('input[type="radio"]').checked = true;
        });
    });

    // Phone number formatting
    phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 13) {
            this.value = this.value.slice(0, 13);
        }
    });

    // Email validation
    emailInput.addEventListener('blur', function() {
        const email = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.setCustomValidity('Format email tidak valid');
        } else {
            this.setCustomValidity('');
        }
    });

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        submitButton.disabled = true;
        submitButton.querySelector('.btn-text').style.display = 'none';
        submitButton.querySelector('.btn-loading').style.display = 'inline-block';
        
        // Clear previous messages
        messagesDiv.style.display = 'none';
        messagesDiv.className = 'form-messages';
        
        // Validate form
        const formData = new FormData(form);
        const amount = parseInt(formData.get('amount'));
        
        if (amount < 10000) {
            showMessage('error', 'Minimal donasi adalah Rp 10.000');
            resetSubmitButton();
            return;
        }
        
        // Submit form via AJAX
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', data.message || 'Donasi berhasil diproses!');
                
                // Redirect to payment page if provided
                if (data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 2000);
                }
            } else {
                showMessage('error', data.message || 'Terjadi kesalahan saat memproses donasi.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('error', 'Terjadi kesalahan koneksi. Silakan coba lagi.');
        })
        .finally(() => {
            resetSubmitButton();
        });
    });
    
    function showMessage(type, message) {
        messagesDiv.className = `form-messages ${type}`;
        messagesDiv.innerHTML = message;
        messagesDiv.style.display = 'block';
        
        // Scroll to message
        messagesDiv.scrollIntoView({ behavior: 'smooth' });
    }
    
    function resetSubmitButton() {
        submitButton.disabled = false;
        submitButton.querySelector('.btn-text').style.display = 'inline-block';
        submitButton.querySelector('.btn-loading').style.display = 'none';
    }

    // Initialize first payment method as selected
    if (paymentOptions.length > 0) {
        paymentOptions[0].classList.add('selected');
    }
});
</script>

</body>
</html>