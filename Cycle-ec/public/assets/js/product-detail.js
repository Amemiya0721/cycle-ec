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
    initImageZoom();
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
    var mainImageLink = document.getElementById('pdMainImageLink');
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
        if (mainImageLink) {
          mainImageLink.setAttribute('href', url);
          mainImageLink.dataset.mainGalleryIndex = button.dataset.imageIndex || '0';
        }

        thumbButtons.forEach(function (btn) {
          btn.classList.remove('is-active');
        });
        button.classList.add('is-active');
      });
    });
  }

  function initImageZoom() {
    var mainGallery = readGalleryData('pd-main-gallery-data');
    var conditionGallery = readGalleryData('pd-condition-gallery-data');
    if (!mainGallery.length && !conditionGallery.length) {
      return;
    }

    var cdnBase = 'https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/';
    var lightboxPromises = Object.create(null);

    function readGalleryData(elementId) {
      var element = document.getElementById(elementId);
      if (!element) {
        return [];
      }
      try {
        var items = JSON.parse(element.textContent);
        return Array.isArray(items) ? items : [];
      } catch (error) {
        return [];
      }
    }

    function getLightbox(key, dataSource) {
      if (!lightboxPromises[key]) {
        lightboxPromises[key] = import(cdnBase + 'photoswipe-lightbox.esm.js')
          .then(function (module) {
            var lightbox = new module.default({
              dataSource: dataSource,
              pswpModule: function () {
                return import(cdnBase + 'photoswipe.esm.js');
              },
              bgOpacity: 0.94,
              wheelToZoom: true,
              closeTitle: '画像を閉じる',
              zoomTitle: '画像を拡大',
              arrowPrevTitle: '前の画像',
              arrowNextTitle: '次の画像',
              indexIndicatorSep: ' / '
            });
            lightbox.init();
            return lightbox;
          });
      }
      return lightboxPromises[key];
    }

    function bindZoomLinks(selector, key, dataSource, indexAttribute) {
      document.querySelectorAll(selector).forEach(function (link) {
        link.addEventListener('click', function (event) {
          if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
          }
          event.preventDefault();
          var index = Number(link.dataset[indexAttribute]);
          if (!Number.isInteger(index) || index < 0 || index >= dataSource.length) {
            return;
          }

          getLightbox(key, dataSource)
            .then(function (lightbox) {
              lightbox.loadAndOpen(index);
            })
            .catch(function () {
              window.open(link.href, '_blank', 'noopener,noreferrer');
            });
        });
      });
    }

    if (mainGallery.length) {
      bindZoomLinks('#pdMainImageLink', 'main', mainGallery, 'mainGalleryIndex');
    }
    if (conditionGallery.length) {
      bindZoomLinks('[data-condition-gallery-index]', 'condition', conditionGallery, 'conditionGalleryIndex');
    }
  }
})();
