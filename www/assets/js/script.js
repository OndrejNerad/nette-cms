// import "swiperjs/swiper-bundle.min.js";

$(function() {

    /**
     * PRODUCTS CAROUSEL
     */
    const productsSwiper = new Swiper('.product-carousel .swiper-wrapper', {
        slidesPerView: 1.2,
        spaceBetween: 43,
        loop: false,
    });

    console.log(productsSwiper);

    console.log('end of script');

});