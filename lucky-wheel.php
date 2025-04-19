<?php

/**
 * Plugin Name: Lucky Wheel
 * Plugin URI: https://example.com/lucky-wheel
 * Description: A simple and beautiful lucky wheel lottery plugin that supports coupons and custom prize types.
 * Version: 1.2.24
 * Author: James Wu
 * Author URI: https://example.com
 * Text Domain: lucky-wheel
 */

// 如果直接访问此文件，则退出
if (!defined('ABSPATH')) {
  exit;
}

// 定义插件路径和URL常量
define('LUCKY_WHEEL_VERSION', '1.2.24');
define('LUCKY_WHEEL_PATH', plugin_dir_path(__FILE__));
define('LUCKY_WHEEL_URL', plugin_dir_url(__FILE__));

// 激活插件时创建数据表
register_activation_hook(__FILE__, 'lucky_wheel_activate');
function lucky_wheel_activate()
{
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();

  // 奖品表
  $table_prizes = $wpdb->prefix . 'lucky_wheel_prizes';
  $sql1 = "CREATE TABLE $table_prizes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        type varchar(50) NOT NULL,
        image_url varchar(255),
        coupon_code varchar(255),
        attr varchar(255),
        probability float NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

  // 参与记录表
  $table_records = $wpdb->prefix . 'lucky_wheel_records';
  $sql2 = "CREATE TABLE $table_records (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        prize_id mediumint(9) NOT NULL,
        ip_address varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql1);
  dbDelta($sql2);

  // 添加默认设置
  add_option('lucky_wheel_settings', array(
    'wheel_background' => '',
    'wheel_pointer' => '',
    'outer_radius' => 200,
    'pointer_bottom_height' => 0,
    'slice_image_scale_factor' => 1,
    'slice_line_color' => '#FFE6B4',
    'button_color' => '#000000',
    'button_text_color' => '#ffffff',
  ));
}

// 卸载插件时删除数据表
register_uninstall_hook(__FILE__, 'lucky_wheel_uninstall');

function lucky_wheel_uninstall()
{
  global $wpdb;

  // 删除奖品表
  $table_prizes = $wpdb->prefix . 'lucky_wheel_prizes';
  $wpdb->query("DROP TABLE IF EXISTS $table_prizes");

  // 删除参与记录表
  $table_records = $wpdb->prefix . 'lucky_wheel_records';
  $wpdb->query("DROP TABLE IF EXISTS $table_records");

  // 删除插件设置
  delete_option('lucky_wheel_settings');
}

// 注册插件设置页面
add_action('admin_menu', 'lucky_wheel_admin_menu');
function lucky_wheel_admin_menu()
{
  add_menu_page(
    'Lucky Wheel Settings',
    'Lucky Wheel',
    'manage_options',
    'lucky-wheel-settings',
    'lucky_wheel_settings_page',
    'dashicons-marker',
    30
  );

  add_submenu_page(
    'lucky-wheel-settings',
    'Prize Manage',
    'Prize Manage',
    'manage_options',
    'lucky-wheel-prizes',
    'lucky_wheel_prizes_page'
  );

  add_submenu_page(
    'lucky-wheel-settings',
    'Draw Records',
    'Draw Records',
    'manage_options',
    'lucky-wheel-records',
    'lucky_wheel_records_page'
  );
}

// 加载后台样式和脚本
add_action('admin_enqueue_scripts', 'lucky_wheel_admin_scripts');
function lucky_wheel_admin_scripts($hook)
{
  if (strpos($hook, 'lucky-wheel') !== false) {
    wp_enqueue_style('lucky-wheel-admin-css', LUCKY_WHEEL_URL . 'assets/admin/css/admin.min.css', array(), LUCKY_WHEEL_VERSION);

    wp_enqueue_media();

    // 添加颜色选择器
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
  }
}

// 基本设置页面
function lucky_wheel_settings_page()
{
  // 检查权限
  if (!current_user_can('manage_options')) {
    return;
  }

  // 保存设置
  if (isset($_POST['lucky_wheel_settings_submit'])) {
    check_admin_referer('lucky_wheel_settings_nonce');

    $settings = array(
      'wheel_background' => esc_url_raw($_POST['wheel_background']),
      'wheel_pointer' => esc_url_raw($_POST['wheel_pointer']),
      'outer_radius' => floatval($_POST['outer_radius']),
      'pointer_bottom_height' => floatval($_POST['pointer_bottom_height']),
      'slice_image_scale_factor' => floatval($_POST['slice_image_scale_factor']),
      'slice_line_color' => sanitize_text_field($_POST['slice_line_color']),
      'button_color' => sanitize_text_field($_POST['button_color']),
      'button_text_color' => sanitize_text_field($_POST['button_text_color']),
    );

    update_option('lucky_wheel_settings', $settings);
    echo '<div class="notice notice-success is-dismissible"><p>The settings are saved.</p></div>';
  }

  // 获取当前设置
  $settings = get_option('lucky_wheel_settings', []);

?>
  <div class="wrap">
    <h1>Lucky Wheel Settings</h1>

    <form method="post" action="">
      <?php wp_nonce_field('lucky_wheel_settings_nonce'); ?>

      <table class="form-table">
        <tr>
          <th scope="row">Turntable background</th>
          <td>
            <div class="image-upload-container">
              <input type="hidden" name="wheel_background" id="wheel_background" value="<?php echo esc_attr($settings['wheel_background']); ?>">
              <button type="button" class="button upload-image-button" data-target="wheel_background">Select image</button>
              <div class="image-preview">
                <?php if (!empty($settings['wheel_background'])) : ?>
                  <img src="<?php echo esc_url($settings['wheel_background']); ?>" alt="Turntable background preview" style="max-width: 200px; max-height: 200px;">
                <?php endif; ?>
              </div>
            </div>
            <p class="description">Upload a background image for the turntable</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Turntable pointer background</th>
          <td>
            <div class="image-upload-container">
              <input type="hidden" name="wheel_pointer" id="wheel_pointer" value="<?php echo esc_attr($settings['wheel_pointer']); ?>">
              <button type="button" class="button upload-image-button" data-target="wheel_pointer">Select image</button>
              <div class="image-preview">
                <?php if (!empty($settings['wheel_pointer'])) : ?>
                  <img src="<?php echo esc_url($settings['wheel_pointer']); ?>" alt="Turntable pointer background preview" style="max-width: 200px; max-height: 200px;">
                <?php endif; ?>
              </div>
            </div>
            <p class="description">Upload the turntable pointer image</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Turntable radius</th>
          <td>
            <input type="number" name="outer_radius" value="<?php echo esc_attr($settings['outer_radius']); ?>" min="0" max="500" step="0.01">
          </td>
        </tr>
        <tr>
          <th scope="row">Height of the fixed axis of the pointer</th>
          <td>
            <input type="number" name="pointer_bottom_height" value="<?php echo esc_attr($settings['pointer_bottom_height']); ?>" min="0" max="200" step="0.01">
          </td>
        </tr>
        <tr>
          <th scope="row">Slice Image Scale Factor</th>
          <td>
            <input type="number" name="slice_image_scale_factor" value="<?php echo esc_attr($settings['slice_image_scale_factor']); ?>" min="0" max="2" step="0.01">
          </td>
        </tr>
        <tr>
          <th scope="row">Slice line color</th>
          <td>
            <input type="text" name="slice_line_color" value="<?php echo esc_attr($settings['slice_line_color']); ?>" class="lucky-wheel-color-picker">
          </td>
        </tr>
        <tr>
          <th scope="row">Button Color</th>
          <td>
            <input type="text" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>" class="lucky-wheel-color-picker">
          </td>
        </tr>
        <tr>
          <th scope="row">Button text color</th>
          <td>
            <input type="text" name="button_text_color" value="<?php echo esc_attr($settings['button_text_color']); ?>" class="lucky-wheel-color-picker">
          </td>
        </tr>
      </table>

      <p class="submit">
        <input type="submit" name="lucky_wheel_settings_submit" class="button-primary" value="Save Settings">
      </p>
    </form>

    <div class="lucky-wheel-shortcode-info">
      <h2>How to use short codes</h2>
      <p>Place the following shortcode on the page or post where you want the carousel to appear:</p>
      <code>[lucky_wheel type='']</code>
      <p>type refers to the value of the prize attribute</p>
    </div>
  </div>

  <script>
    jQuery(document).ready(function($) {
      // 颜色选择器初始化
      $('.lucky-wheel-color-picker').wpColorPicker();

      // 图片上传功能
      $('.upload-image-button').click(function(e) {
        e.preventDefault();

        var button = $(this);
        var targetInput = $('#' + button.data('target'));
        var imagePreview = button.closest('td').find('.image-preview');

        var frame = wp.media({
          title: 'Select image',
          button: {
            text: 'Use this image'
          },
          multiple: false
        });

        frame.on('select', function() {
          var attachment = frame.state().get('selection').first().toJSON();
          targetInput.val(attachment.url);
          imagePreview.html('<img src="' + attachment.url + '" alt="Image Preview" style="max-width: 200px; max-height: 200px;">');
        });

        frame.open();
      });
    });
  </script>
<?php
}

// 奖品管理页面
function lucky_wheel_prizes_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'lucky_wheel_prizes';

  // 检查权限
  if (!current_user_can('manage_options')) {
    return;
  }

  // 处理删除奖品操作
  if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['prize_id'])) {
    $prize_id = intval($_GET['prize_id']);
    check_admin_referer('delete_prize_' . $prize_id);

    $wpdb->delete(
      $table_name,
      array('id' => $prize_id),
      array('%d')
    );

    // 重定向到没有参数的URL
    $redirect_url = remove_query_arg(array('action', 'prize_id', '_wpnonce'));

    wp_redirect($redirect_url);

    exit;
  }

  // 处理奖品添加或编辑
  if (isset($_POST['lucky_wheel_prizes_submit'])) {
    check_admin_referer('lucky_wheel_prizes_nonce');

    // 清空现有奖品
    $wpdb->query("TRUNCATE TABLE $table_name");

    $names = isset($_POST['prize_name']) ? $_POST['prize_name'] : array();
    $types = isset($_POST['prize_type']) ? $_POST['prize_type'] : array();
    $images = isset($_POST['prize_image']) ? $_POST['prize_image'] : array();
    $coupons = isset($_POST['prize_coupon']) ? $_POST['prize_coupon'] : array();
    $attrs = isset($_POST['prize_attr']) ? $_POST['prize_attr'] : array();
    $probabilities = isset($_POST['prize_probability']) ? $_POST['prize_probability'] : array();

    $total_probability = 0;
    foreach ($probabilities as $prob) {
      $total_probability += floatval($prob);
    }

    // 添加奖品
    for ($i = 0; $i < count($names); $i++) {
      if (!empty($names[$i])) { // 只添加有名称的奖品
        $wpdb->insert(
          $table_name,
          array(
            'name' => sanitize_text_field($names[$i]),
            'type' => sanitize_text_field($types[$i]),
            'image_url' => $types[$i] != 'coupon' ? esc_url_raw($images[$i]) : '',
            'coupon_code' => $types[$i] == 'coupon' ? sanitize_text_field($coupons[$i]) : '',
            'attr' => $attrs[$i] ? sanitize_text_field($attrs[$i]) : '',
            'probability' => floatval($probabilities[$i])
          )
        );
      }
    }
    echo '<div class="notice notice-success is-dismissible"><p>Prize settings saved.</p></div>';
  }

  // 获取现有奖品
  $prizes = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
?>
  <div class="wrap">
    <h1>Prize Management</h1>

    <form method="post" action="" id="prizes-form">
      <?php wp_nonce_field('lucky_wheel_prizes_nonce'); ?>

      <div class="tablenav top">
        <div class="alignleft actions">
          <button type="button" id="add-prize-button" class="button">Add New Prize</button>
        </div>
        <br class="clear">
      </div>

      <table class="wp-list-table widefat fixed striped" id="prizes-table">
        <thead>
          <tr>
            <th width="5%">Prize ID</th>
            <th width="20%">Prize Name</th>
            <th width="15%">Prize Type</th>
            <th width="25%">Coupon code/prize image</th>
            <th width="15%">Prize attributes</th>
            <th width="10%">Probability(%)</th>
            <th width="10%">Operation</th>
          </tr>
        </thead>
        <tbody id="prizes-body">
          <?php foreach ($prizes ?? [] as $index => $prize) : ?>
            <tr class="prize-row">
              <td class="prize-index"><?php echo $index + 1; ?></td>
              <td>
                <input type="text" name="prize_name[]" value="<?php echo esc_attr($prize->name); ?>" required>
              </td>
              <td>
                <select name="prize_type[]" class="prize-type-select" data-index="<?php echo $index; ?>" required>
                  <option value="custom" <?php selected($prize->type, 'custom'); ?>>Custom Prizes</option>
                  <option value="coupon" <?php selected($prize->type, 'coupon'); ?>>Coupons</option>
                  <option value="no" <?php selected($prize->type, 'no'); ?>>No Winning</option>
                </select>
              </td>
              <td>
                <div class="prize-coupon prize-field-<?php echo $index; ?>" <?php echo $prize->type == 'coupon' ? '' : 'style="display:none;"'; ?>>
                  <input type="text" name="prize_coupon[]" placeholder="Enter coupon code" value="<?php echo esc_attr($prize->coupon_code); ?>">
                </div>
                <div class="prize-image prize-field-<?php echo $index; ?>" <?php echo $prize->type != 'coupon' ? '' : 'style="display:none;"'; ?>>
                  <input type="hidden" name="prize_image[]" class="prize-image-url" value="<?php echo esc_url($prize->image_url); ?>">
                  <button type="button" class="button upload-image-button">Select image</button>
                  <div class="image-preview">
                    <?php if (!empty($prize->image_url)) : ?>
                      <img src="<?php echo esc_url($prize->image_url); ?>" alt="Prize Image Preview" style="max-width: 100px; max-height: 100px;">
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <input type="text" name="prize_attr[]" placeholder="Enter the prize attributes" value="<?php echo esc_attr($prize->attr); ?>">
              </td>
              <td>
                <input type="number" name="prize_probability[]" value="<?php echo esc_attr($prize->probability); ?>" min="0" max="100" step="0.01" required>
              </td>
              <td>
                <?php if (!empty($prize->id)) : ?>
                  <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'prize_id' => $prize->id)), 'delete_prize_' . $prize->id); ?>" class="delete-prize" onclick="return confirm('Are you sure you want to delete this prize?');">delete</a>
                <?php else : ?>
                  <a href="#" class="delete-prize-row">Deleting a row</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" style="text-align: right;"><strong>Total probability:</strong></td>
            <td><span id="total-probability">0</span>%</td>
            <td></td>
          </tr>
        </tfoot>
      </table>

      <p class="submit">
        <input type="submit" name="lucky_wheel_prizes_submit" class="button-primary" value="Save reward settings">
      </p>
    </form>
  </div>

  <!-- 奖品行模板 (用于JavaScript添加新行) -->
  <template id="prize-row-template">
    <tr class="prize-row">
      <td class="prize-index"></td>
      <td>
        <input type="text" name="prize_name[]" value="" required>
      </td>
      <td>
        <select name="prize_type[]" class="prize-type-select" data-index="__INDEX__">
          <option value="custom">Custom Prizes</option>
          <option value="coupon">Coupons</option>
          <option value="no">No Winning</option>
        </select>
      </td>
      <td>
        <div class="prize-image prize-field-__INDEX__">
          <input type="hidden" name="prize_image[]" class="prize-image-url" value="">
          <button type="button" class="button upload-image-button">Select image</button>
          <div class="image-preview"></div>
        </div>
        <div class="prize-coupon prize-field-__INDEX__" style="display:none;">
          <input type="text" name="prize_coupon[]" placeholder="Enter coupon code" value="">
        </div>
      </td>
      <td>
        <input type="text" name="prize_attr[]" placeholder="输入奖品属性" data-index="__INDEX__">
      </td>
      <td>
        <input type="number" name="prize_probability[]" value="0" min="0" max="100" step="0.01" required>
      </td>
      <td>
        <a href="#" class="delete-prize-row">Deleting a row</a>
      </td>
    </tr>
  </template>

  <script>
    jQuery(document).ready(function($) {
      // 上传图片
      function setupImageUpload() {
        $('.upload-image-button').off('click').on('click', function(e) {
          e.preventDefault();

          var button = $(this);
          var imagePreview = button.siblings('.image-preview');
          var imageUrl = button.siblings('.prize-image-url');

          var frame = wp.media({
            title: 'Select Prize Image',
            button: {
              text: 'Use this image'
            },
            multiple: false
          });

          frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            imageUrl.val(attachment.url);
            imagePreview.html('<img src="' + attachment.url + '" alt="Prize Image Preview" style="max-width: 100px; max-height: 100px;">');
          });

          frame.open();
        });
      }

      // 切换奖品类型
      function setupPrizeTypeChange() {
        $('.prize-type-select').off('change').on('change', function() {
          var index = $(this).data('index');
          var type = $(this).val();

          if (type === 'coupon') {
            $('.prize-coupon.prize-field-' + index).show();
            $('.prize-image.prize-field-' + index).hide();
          } else {
            $('.prize-coupon.prize-field-' + index).hide();
            $('.prize-image.prize-field-' + index).show();
          }
        });
      }

      // 计算概率总和
      function calculateTotalProbability() {
        var total = 0;
        $('input[name="prize_probability[]"]').each(function() {
          total += parseFloat($(this).val()) || 0;
        });
        $('#total-probability').text(total.toFixed(2));

        // 检查总和是否为100%
        if (Math.abs(total - 100) > 0.01) {
          $('#total-probability').css('color', 'red');
        } else {
          $('#total-probability').css('color', 'green');
        }
      }

      // 更新所有行的序号
      function updateRowIndexes() {
        $('.prize-row').each(function(index) {
          $(this).find('.prize-index').text(index + 1);
          $(this).find('.prize-type-select').data('index', index).attr('data-index', index);
          $(this).find('.prize-coupon').removeClass(function(i, className) {
            return (className.match(/(^|\s)prize-field-\S+/g) || []).join(' ');
          }).addClass('prize-field-' + index);
          $(this).find('.prize-image').removeClass(function(i, className) {
            return (className.match(/(^|\s)prize-field-\S+/g) || []).join(' ');
          }).addClass('prize-field-' + index);
        });
      }

      // 添加新奖品行
      $('#add-prize-button').click(function() {
        var template = $('#prize-row-template').html();
        var index = $('.prize-row').length;

        // 替换模板中的索引
        template = template.replace(/__INDEX__/g, index);

        // 添加新行
        $('#prizes-body').append(template);

        // 更新索引编号
        updateRowIndexes();

        // 重新初始化事件
        setupImageUpload();
        setupPrizeTypeChange();
        calculateTotalProbability();

        // 绑定删除行事件
        bindDeleteRowEvent();
      });

      // 绑定删除行事件
      function bindDeleteRowEvent() {
        $('.delete-prize-row').off('click').on('click', function(e) {
          e.preventDefault();

          // 如果表格只有一行，不允许删除
          if ($('.prize-row').length <= 1) {
            alert('At least one prize must be reserved!');
            return false;
          }

          $(this).closest('tr').remove();
          updateRowIndexes();
          calculateTotalProbability();
        });
      }

      // 初始化
      setupImageUpload();
      setupPrizeTypeChange();
      calculateTotalProbability();
      bindDeleteRowEvent();

      // 监听概率变化
      $(document).on('input', 'input[name="prize_probability[]"]', calculateTotalProbability);
    });
  </script>
<?php
}

// 抽奖记录页面
// 抽奖记录页面
function lucky_wheel_records_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'lucky_wheel_records';
  $prizes_table = $wpdb->prefix . 'lucky_wheel_prizes';

  // 检查权限
  if (!current_user_can('manage_options')) {
    return;
  }

  // 处理批量删除操作
  if (isset($_POST['action']) && $_POST['action'] == 'bulk_delete' && isset($_POST['record_ids']) && is_array($_POST['record_ids'])) {
    // 验证安全nonce
    check_admin_referer('bulk_delete_records', 'bulk_delete_nonce');

    $ids = array_map('intval', $_POST['record_ids']);

    if (!empty($ids)) {
      $ids_string = implode(',', $ids);
      $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids_string)");

      // 添加成功消息
      $count = count($ids);
      $message = sprintf(_n('%s record deleted.', '%s records deleted.', $count), $count);
      echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
    }
  }

  // 每页显示记录数
  $per_page = 20;
  $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $offset = ($current_page - 1) * $per_page;

  // 获取记录总数
  $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
  $total_pages = ceil($total_records / $per_page);

  // 获取抽奖记录
  $records = $wpdb->get_results("
      SELECT r.id, r.email, r.ip_address, r.created_at, p.name as prize_name, p.type as prize_type
      FROM $table_name r
      LEFT JOIN $prizes_table p ON r.prize_id = p.id
      ORDER BY r.created_at DESC
      LIMIT $offset, $per_page
  ");

  // 添加必要的JS，用于全选/取消全选功能
?>
  <script type="text/javascript">
    jQuery(document).ready(function($) {
      // 全选/取消全选
      $('#select-all').click(function() {
        $('.record-checkbox').prop('checked', this.checked);
      });

      // 如果取消选中某个复选框，则"全选"也取消选中
      $('.record-checkbox').click(function() {
        if (!this.checked) {
          $('#select-all').prop('checked', false);
        } else {
          // 检查是否所有复选框都被选中
          var allChecked = true;
          $('.record-checkbox').each(function() {
            if (!this.checked) {
              allChecked = false;
              return false;
            }
          });
          $('#select-all').prop('checked', allChecked);
        }
      });

      // 确认删除
      $('#bulk-delete-btn, #bulk-delete-btn-bottom').click(function() {
        if ($('.record-checkbox:checked').length == 0) {
          alert('Please select at least one record to delete.');
          return false;
        }
        return confirm('Are you sure you want to delete selected records? This action cannot be undone.');
      });
    });
  </script>

  <div class="wrap">
    <h1>Lottery records</h1>

    <form method="post" id="records-form">
      <?php wp_nonce_field('bulk_delete_records', 'bulk_delete_nonce'); ?>
      <input type="hidden" name="action" value="bulk_delete">

      <div class="tablenav top">
        <div class="alignleft actions bulkactions">
          <input type="submit" id="bulk-delete-btn" class="button action" value="Delete Selected">
        </div>
        <?php if ($total_pages > 1) : ?>
          <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_records; ?> Records</span>
            <span class="pagination-links">
              <?php
              echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page
              ));
              ?>
            </span>
          </div>
        <?php endif; ?>
      </div>

      <table class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th width="1%" class="check-column">
              <input type="checkbox" id="select-all">
            </th>
            <th width="5%">ID</th>
            <th width="20%">Mail</th>
            <th width="20%">IP</th>
            <th width="20%">Prize Name</th>
            <th width="15%">Prize Type</th>
            <th width="15%">Draw Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)) : ?>
            <tr>
              <td colspan="7">No lottery record</td>
            </tr>
          <?php else : ?>
            <?php foreach ($records as $record) : ?>
              <tr>
                <th scope="row" class="check-column">
                  <input type="checkbox" class="record-checkbox" name="record_ids[]" value="<?php echo esc_attr($record->id); ?>">
                </th>
                <td><?php echo esc_html($record->id); ?></td>
                <td><?php echo esc_html($record->email); ?></td>
                <td><?php echo isset($record->ip_address) ? $record->ip_address : ''; ?></td>
                <td><?php echo esc_html($record->prize_name); ?></td>
                <td><?php echo $record->prize_type == 'coupon' ? 'Coupons' : 'Custom Prizes'; ?></td>
                <td><?php echo esc_html($record->created_at); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
          <div class="alignleft actions bulkactions">
            <input type="submit" id="bulk-delete-btn-bottom" class="button action" value="Delete Selected">
          </div>
          <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_records; ?> Records</span>
            <span class="pagination-links">
              <?php
              echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page
              ));
              ?>
            </span>
          </div>
        </div>
      <?php endif; ?>
    </form>
  </div>
<?php
}

// 前端显示 - 注册短代码
add_shortcode('lucky_wheel', 'lucky_wheel_shortcode');
function lucky_wheel_shortcode($atts)
{
  // 加载Tweenmax、Winwheel.js库
  wp_enqueue_script('tweenmax-js', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/2.1.3/TweenMax.min.js', array(), '2.1.3', true);
  wp_enqueue_script('winwheel-js', LUCKY_WHEEL_URL . 'assets/js/Winwheel.min.js', array(), LUCKY_WHEEL_VERSION, true);

  // 加载前端样式和脚本
  wp_enqueue_style('lucky-wheel-css', LUCKY_WHEEL_URL . 'assets/css/style.min.css', array(), LUCKY_WHEEL_VERSION);
  wp_enqueue_script('lucky-wheel-js', LUCKY_WHEEL_URL . 'assets/js/wheel.min.js', array('jquery'), LUCKY_WHEEL_VERSION, true);

  if(!empty($_GET["type"])){
	  // url参数
	  $params_atts = array(
		  'type' => $_GET["type"]
	  );
  }

  // 合并用户设置的属性和默认属性
  $atts = shortcode_atts($atts, $params_atts, 'lucky_wheel');

  // 获取设置和奖品数据
  $settings = get_option('lucky_wheel_settings');

  global $wpdb;
  $table_name = $wpdb->prefix . 'lucky_wheel_prizes';
  // OR if you just want to SELECT with the attr filter directly in SQL:
  $attr_value = $wpdb->prepare('%s', sanitize_text_field($atts['type']));
  $prizes = $wpdb->get_results("SELECT * FROM $table_name WHERE attr = {$attr_value} ORDER BY id ASC");

  // 检查是否配置了奖品
  if (empty($prizes)) {
    return '<div class="lucky-wheel-error">转盘奖品未配置，请联系管理员。</div>';
  }

  // 准备奖品数据用于JavaScript
  $prizes_data = array();
  foreach ($prizes as $prize) {
    $prizes_data[] = array(
      'id' => $prize->id,
      'name' => $prize->name,
      'type' => $prize->type,
      'image_url' => $prize->image_url,
      'probability' => $prize->probability
    );
  }

  // 传递数据到JavaScript
  wp_localize_script('lucky-wheel-js', 'luckyWheelData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('lucky_wheel_nonce'),
    'prizes' => $prizes_data,
    'settings' => $settings,
    'prize_attr' => $atts['type']
  ));

  // 开始输出缓冲区
  ob_start();
?>
  <div class="lucky-wheel-container">
    <div class="lucky-wheel-form">
      <div style="margin-bottom: 10px;font-size: 16px;font-weight: bold;">Enter your email for a chance to win!</div>
      <div class="email-input-container" style='display:flex;align-items:center;justify-content: space-between;max-width:100%'>
        <input type="email" id="lucky-wheel-email" placeholder="Please enter your email" style='flex-shrink: 1' required>
        <button id="lucky-wheel-start" style="background-color: <?php echo esc_attr($settings['button_color']); ?>; color: <?php echo esc_attr($settings['button_text_color']); ?>;flex-shrink: 0;margin-left:10px">Spin Now!</button>
      </div>
    </div>

    <div class="lucky-wheel-wrapper">
      <div id="lucky-wheel-canvas-container">
        <canvas id="lucky-wheel-canvas" width="500" height="500">
          Canvas is not supported, please use a modern browser
        </canvas>
      </div>
    </div>

    <div id="lucky-wheel-result" class="lucky-wheel-result" style="display: none;">
      <div class="result-content">
        <h3 id="result-title"></h3>
        <p id="result-message"></p>
        <div id="result-image-container"></div>
        <button id="lucky-wheel-close" style="background-color: <?php echo esc_attr($settings['button_color']); ?>; color: <?php echo esc_attr($settings['button_text_color']); ?>;">Close</button>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}

// 处理AJAX请求
add_action('wp_ajax_lucky_wheel_spin', 'lucky_wheel_spin_callback');
add_action('wp_ajax_nopriv_lucky_wheel_spin', 'lucky_wheel_spin_callback');
function lucky_wheel_spin_callback()
{
  // 验证nonce
  check_ajax_referer('lucky_wheel_nonce', 'nonce');

  $email = sanitize_email($_POST['email']);

  // 验证邮箱
  if (!is_email($email)) {
    wp_send_json_error(array('message' => 'Please enter a valid email address'));
    wp_die();
  }

  // 获取用户 IP 地址的函数
  function getCloudflareVisitorIP()
  {
    // Cloudflare特定的请求头
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
      return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    // 备用方法 - 标准代理检测
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      // HTTP_X_FORWARDED_FOR可能包含多个IP，第一个通常是客户端IP
      $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim($ips[0]);
    }

    // 如果都获取不到，使用REMOTE_ADDR
    if (!empty($_SERVER['REMOTE_ADDR'])) {
      return $_SERVER['REMOTE_ADDR'];
    }

    // 最后的防御措施 - 提供默认IP
    return '0.0.0.0';
  }

  // 检查邮箱是否已经参与过抽奖
  global $wpdb;
  $records_table = $wpdb->prefix . 'lucky_wheel_records';
  $existed = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $records_table WHERE email = %s",
    $email
  ));

  if ($existed > 0) {
    wp_send_json_error(array('message' => 'This email has participated in the lucky draw'));
    wp_die();
  }

  $prize_attr = sanitize_text_field($_POST['prize_attr']);

  // 获取奖品数据
  $prizes_table = $wpdb->prefix . 'lucky_wheel_prizes';
  // OR if you just want to SELECT with the attr filter directly in SQL:
  $attr_value = $wpdb->prepare('%s', $prize_attr);
  $prizes = $wpdb->get_results("SELECT * FROM $prizes_table WHERE attr = {$attr_value} ORDER BY id ASC");

  // 根据概率选择奖品
  $total = 0;
  $probabilities = array();
  foreach ($prizes as $prize) {
    $total += $prize->probability;
    $probabilities[] = $total;
  }

  $random = mt_rand(0, 10000) / 100; // 0-100随机数

  $selected_prize = null;
  for ($i = 0; $i < count($probabilities); $i++) {
    if ($random <= $probabilities[$i]) {
      $selected_prize = $prizes[$i];
      break;
    }
  }

  // 如果没有选中任何奖品，选择最后一个
  if ($selected_prize === null) {
    $selected_prize = end($prizes);
  }

  if ($selected_prize->prizeType !== 'no') {
    // 保存中奖抽奖记录
    $wpdb->insert($records_table, array(
      'email' => $email,
      'prize_id' => $selected_prize->id,
      'ip_address' => getCloudflareVisitorIP(),
      'created_at' => current_time('mysql')
    ));
  }

  // 返回奖品数据
  wp_send_json_success(array(
    'prizeIndex' => array_search($selected_prize, $prizes),
    'prizeId' => $selected_prize->id,
    'prizeName' => $selected_prize->name,
    'prizeType' => $selected_prize->type,
    'imageUrl' => $selected_prize->image_url,
    'couponCode' => $selected_prize->coupon_code
  ));

  wp_die();
}
