document.addEventListener('DOMContentLoaded', () => {

    const recommendSwiper = document.querySelector('.recommend-swiper');

    if (!recommendSwiper) {
        return;
    }

    new Swiper(recommendSwiper, {

        slidesPerView: 1,

        spaceBetween: 16,

        navigation: {
            nextEl: recommendSwiper.querySelector('.swiper-button-next'),
            prevEl: recommendSwiper.querySelector('.swiper-button-prev')
        },

        pagination: {
            el: recommendSwiper.querySelector('.swiper-pagination'),
            clickable: true
        },

        breakpoints: {

            576: {
                slidesPerView: 2
            },

            768: {
                slidesPerView: 3
            },

            1200: {
                slidesPerView: 4
            }

        }

    });

});