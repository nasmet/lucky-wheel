jQuery(document).ready(function ($) {
  // 全局变量
  let theWheel;
  let wheelSpinning = false;
  let selectedPrize = null;

  // 初始化转盘
  function initWheel() {
    const prizes = luckyWheelData.prizes;
    const settings = luckyWheelData.settings;
    const numPrizes = prizes.length;

    // 创建转盘切片数据
    const segments = [];

    for (let i = 0; i < numPrizes; i++) {
      const prize = prizes[i];
      const segment = {
        text: prize.name,
        textFillStyle: 'black',
      };

      if (prize.image_url) {
        segment.image = prize.image_url;
        segment.textFillStyle = 'white';
      }

      segments.push(segment);
    }

    // 创建转盘
    theWheel = new Winwheel({
      canvasId: 'lucky-wheel-canvas',
      numSegments: numPrizes,
      sliceImageScaleFactor: settings.slice_image_scale_factor || 1,
      outerRadius: settings.outer_radius,
      rotationAngle: 0,
      wheelImage: '',
      pointerGuide: {
        display: true,
      },
      pointerImg: '',
      pointerBottomHeight: settings.pointer_bottom_height,
      strokeStyle: settings.slice_line_color || 'grey', // Segment line colour. Again segment lines only drawn if this is specified.
      lineWidth: 1, // Width of lines around segments.
      drawText: true,
      textFontSize: 16,
      textAlignment: 'center',
      textOrientation: 'horizontal', // Set orientation. horizontal, vertical, curved.
      textMargin: 15,
      textFontFamily: 'Arimo',
      drawMode: 'segmentImage',
      segments: segments,
      imageOverlay: true,
      animation: {
        type: 'spinToStop',
        duration: 5,
        spins: 8,
        callbackFinished: alertPrize,
      },
    });

    // 预加载图片
    loadImages();
  }

  // 预加载图片
  function loadImages() {
    const prizes = luckyWheelData.prizes;
    let loadedImages = 0;
    let numImages = 0;

    // 计算需要加载的图片数量
    prizes.forEach(function (prize) {
      if (prize.type === 'custom' && prize.image_url) {
        numImages++;
      }
    });

    // 如果没有图片需要加载，直接返回
    if (numImages === 0) {
      return;
    }

    const settings = luckyWheelData.settings;

    if (settings.wheel_background) {
      theWheel.wheelImage = new Image();
      theWheel.wheelImage.src = settings.wheel_background;
      numImages++;
      theWheel.wheelImage.onload = function () {
        loadedImages++;
        if (loadedImages === numImages) {
          theWheel.draw();
        }
      };
    }

    if (settings.wheel_pointer) {
      theWheel.pointerImg = new Image();
      theWheel.pointerImg.src = settings.wheel_pointer;
      numImages++;
      theWheel.pointerImg.onload = function () {
        loadedImages++;
        if (loadedImages === numImages) {
          theWheel.draw();
        }
      };
    }

    // 加载每个图片
    prizes.forEach(function (prize, index) {
      if (prize.type === 'custom' && prize.image_url) {
        theWheel.segments[index + 1].imgData = new Image();
        theWheel.segments[index + 1].imgData.src = prize.image_url;
        theWheel.segments[index + 1].imgData.onload = function () {
          loadedImages++;
          if (loadedImages === numImages) {
            theWheel.draw();
          }
        };
      }
    });
  }

  // 初始化转盘
  initWheel();

  // 点击开始抽奖
  $('#lucky-wheel-start').click(function () {
    if (wheelSpinning) {
      return;
    }

    const email = $('#lucky-wheel-email').val();
    if (!email) {
      alert('Please enter your email address');
      return;
    }

    // 验证邮箱格式
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
      alert('Please enter a valid email address');
      return;
    }

    // AJAX请求
    $.ajax({
      url: luckyWheelData.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'lucky_wheel_spin',
        email: email,
        prize_attr: luckyWheelData.prize_attr,
        nonce: luckyWheelData.nonce,
      },
      beforeSend: function () {
        // 禁用按钮
        $('#lucky-wheel-start').prop('disabled', true).text('Drawing...');
      },
      success: function (response) {
        if (response.success) {
          // 保存中奖信息
          selectedPrize = response.data;
          const prizes = luckyWheelData.prizes;
          // 计算旋转到的角度
          const prizeIndex = parseInt(selectedPrize.prizeIndex);
          const segmentAngle = 360 / prizes.length;
          const stopAngle =
            (prizeIndex * segmentAngle +
              Math.floor(Math.random() * (segmentAngle - 20) + 10)) %
            360;
          // 设置停止角度并开始旋转
          theWheel.animation.stopAngle = stopAngle;
          theWheel.startAnimation();
          wheelSpinning = true;
        } else {
          alert(response.data.message);
          $('#lucky-wheel-start')
            .prop('disabled', false)
            .text('Start lucky draw');
        }
      },
      error: function () {
        alert('Network error, please try again later');
        $('#lucky-wheel-start')
          .prop('disabled', false)
          .text('Start lucky draw');
      },
    });
  });

  // 转盘停止后显示中奖信息
  function alertPrize() {
    wheelSpinning = false;

    if (!selectedPrize) {
      return;
    }

    if (selectedPrize.prizeType === 'no') {
      // 未中奖提示
      $('#result-title').text('Paws and reflect! Better luck next spin!');
    } else {
      // 中奖提示
      $('#result-title').text('Winner winner! Your prize is locked in! 🎯 ');

      if (selectedPrize.prizeType === 'coupon') {
        $('#result-message').text(
          'Your coupon code: ' + selectedPrize.couponCode
        );

        $('#result-image-container').html(`<p>${selectedPrize.prizeName}</p>`);
      } else {
        $('#result-message').text(
          '(Valid for 24 hours - complete your order now!)'
        );
        if (selectedPrize.imageUrl) {
          $('#result-image-container').html(
            `<img src="${selectedPrize.imageUrl}" alt="Prize pictures">
            <p style='transform: translateY(-50px);color: #333;font-weight: bold;'>${selectedPrize.prizeName}</p>
            `
          );
        }
      }
    }

    // 显示结果弹窗
    $('#lucky-wheel-result').fadeIn(300);

    // 重置按钮
    $('#lucky-wheel-start').prop('disabled', true).text('Participated');
  }

  // 关闭结果弹窗
  $('#lucky-wheel-close').click(function () {
    $('#lucky-wheel-result').fadeOut(300);
  });

  // 点击弹窗背景关闭弹窗
  $('#lucky-wheel-result').click(function (e) {
    if (e.target === this) {
      $(this).fadeOut(300);
    }
  });
});
