<?php if ( ! defined('ABSPATH') ) exit; ?>
<?php
$page        = max(1, (int)($_GET['paged'] ?? 1));
$per_page    = 20;
$redirects   = WSS_Redirects::get_all($per_page, $page);
$total       = WSS_Redirects::count();
$total_pages = ceil($total / $per_page);
?>
<div class="wss-wrap">
    <div class="wss-page-header">
        <h1><span class="dashicons dashicons-randomize"></span> مدیریت ریدایرکت</h1>
        <p>مدیریت ریدایرکت‌های 301 و 302 بدون نیاز به ویرایش htaccess.</p>
    </div>

    <div class="wss-settings-grid">

        <!-- Add Redirect -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-plus-alt"></span> افزودن ریدایرکت جدید</div>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wss_add_redirect">
                <?php wp_nonce_field('wss_add_redirect'); ?>
                <div class="wss-field">
                    <label>URL مبدا <small>(مثال: /old-page)</small></label>
                    <input type="text" name="source_url" class="widefat" placeholder="/old-page-url" required>
                </div>
                <div class="wss-field">
                    <label>URL مقصد</label>
                    <input type="text" name="target_url" class="widefat" placeholder="/new-page-url یا https://..." required>
                </div>
                <div class="wss-field">
                    <label>نوع ریدایرکت</label>
                    <select name="redirect_type">
                        <option value="301">301 - Moved Permanently (توصیه‌شده برای سئو)</option>
                        <option value="302">302 - Found (موقت)</option>
                        <option value="307">307 - Temporary Redirect</option>
                        <option value="308">308 - Permanent Redirect</option>
                    </select>
                </div>
                <button type="submit" class="button button-primary">افزودن ریدایرکت</button>
            </form>
        </div>

        <!-- Import CSV -->
        <div class="wss-card">
            <div class="wss-card-header"><span class="dashicons dashicons-upload"></span> وارد کردن از CSV</div>
            <p class="wss-help">فرمت: <code>source_url,target_url,type</code> (یک ردیف در هر خط)</p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wss_import_redirects">
                <?php wp_nonce_field('wss_import_redirects'); ?>
                <textarea name="csv_content" rows="6" class="widefat" placeholder="/old-page,/new-page,301
/another-old,/another-new,302"></textarea>
                <button type="submit" class="button" style="margin-top:8px">وارد کردن</button>
            </form>
        </div>

    </div>

    <!-- Redirects List -->
    <div class="wss-card" style="margin-top:20px">
        <div class="wss-card-header">
            <span class="dashicons dashicons-list-view"></span>
            لیست ریدایرکت‌ها
            <span class="wss-badge"><?php echo $total; ?></span>
        </div>

        <?php if ($redirects): ?>
        <table class="wss-table wss-table-full">
            <thead>
                <tr>
                    <th>URL مبدا</th>
                    <th>URL مقصد</th>
                    <th>نوع</th>
                    <th>بازدید</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($redirects as $r): ?>
            <tr class="<?php echo $r->enabled ? '' : 'wss-disabled-row'; ?>">
                <td><code><?php echo esc_html($r->source_url); ?></code></td>
                <td class="wss-td-truncate"><?php echo esc_html($r->target_url); ?></td>
                <td><span class="wss-badge <?php echo $r->type == 301 ? 'wss-badge-good' : 'wss-badge-warn'; ?>"><?php echo (int)$r->type; ?></span></td>
                <td><?php echo number_format($r->hits); ?></td>
                <td>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wss_toggle_redirect&id=' . $r->id), 'wss_toggle_redirect'); ?>" class="wss-toggle-link">
                        <span class="wss-status-dot <?php echo $r->enabled ? 'active' : 'inactive'; ?>"></span>
                        <?php echo $r->enabled ? 'فعال' : 'غیرفعال'; ?>
                    </a>
                </td>
                <td>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wss_delete_redirect&id=' . $r->id), 'wss_delete_redirect'); ?>"
                       class="wss-delete-btn"
                       onclick="return confirm('آیا از حذف این ریدایرکت مطمئن هستید؟')">
                        <span class="dashicons dashicons-trash"></span>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="wss-pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?php echo admin_url('admin.php?page=wss-redirects&paged=' . $i); ?>"
               class="button <?php echo $i == $page ? 'button-primary' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="wss-empty-state">
            <span class="dashicons dashicons-randomize"></span>
            <p>هیچ ریدایرکتی تنظیم نشده است.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
