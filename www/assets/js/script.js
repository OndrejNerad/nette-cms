$(function() {

    console.log('start of script');

    /**
     * STICKY HEADER
     */
    const nav = document.querySelector('nav .nav-wrap');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 0) {
            nav.classList.add('sticky');
        } else {
            nav.classList.remove('sticky');
        }
    });


    /**
     * PRODUCTS CAROUSEL
     */
    const productsSwiper = new Swiper('.product-carousel', {
        slidesPerView: 1.2,
        spaceBetween: 43,
        loop: false,
        pagination: {
            el: '.product-pagination'
        }
    });


    /**
     * REVIEWS CAROUSEL
     */
    const reviewsSwiper = new Swiper('.review-carousel', {
        slidesPerView: 3.2,
        spaceBetween: 43,
        loop: false,
    });


    console.log('end of script');

});