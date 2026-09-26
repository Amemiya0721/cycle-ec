/**
 * product-detail.js
 * -----------------------------------------------------------
 * 商品詳細ページの挙動:
 *   1. PC幅でのタブ切り替え（.pd-tab-btn / .pd-panel）
 *   2. サムネイルクリックによるメイン画像の切り替え
 *
 * スマートフォン幅では product-detail.css 側で
 * 全パネルを常時表示に切り替えているため、タブJSの有無に関わらず崩れない。
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initThumbnails();
  });

  function initTabs() {
    var tabButtons = document.querySelectorAll('.pd-tab-btn');
    if (!tabButtons.length) {
      return;
    }

    tabButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var targetId = button.getAttribute('data-target');
        if (!targetId) {
          return;
        }

        tabButtons.forEach(function (btn) {
          btn.classList.remove('is-active');
        });
        button.classList.add('is-active');

        document.querySelectorAll('.pd-panel').forEach(function (panel) {
          panel.classList.toggle('is-active', panel.id === targetId);
        });
      });
    });
  }

  function initThumbnails() {
    var thumbButtons = document.querySelectorAll('.pd-thumb-btn');
    var mainImageTag = document.getElementById('pdMainImageTag');
    if (!thumbButtons.length || !mainImageTag) {
      return;
    }

    thumbButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        var url = button.getAttribute('data-image-url');
        if (!url) {
          return;
        }
        mainImageTag.setAttribute('src', url);

        thumbButtons.forEach(function (btn) {
          btn.classList.remove('is-active');
        });
        button.classList.add('is-active');
      });
    });
  }
})();
