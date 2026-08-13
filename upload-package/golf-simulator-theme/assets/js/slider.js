document.addEventListener('DOMContentLoaded', function () {
    var slider = document.querySelector('.hero-slider');

    if (!slider) {
        return;
    }

    var slides = Array.prototype.slice.call(slider.querySelectorAll('.hero-slide'));
    var prevButton = slider.querySelector('.slider-prev');
    var nextButton = slider.querySelector('.slider-next');
    var dotsContainer = slider.querySelector('.slider-dots');

    if (slides.length <= 1 || !prevButton || !nextButton || !dotsContainer) {
        return;
    }

    var currentIndex = 0;
    var autoplayMs = 5000;
    var timerId = null;

    function setActiveSlide(index) {
        slides.forEach(function (slide, i) {
            slide.classList.toggle('active', i === index);
        });

        Array.prototype.slice.call(dotsContainer.querySelectorAll('.dot')).forEach(function (dot, i) {
            dot.classList.toggle('active', i === index);
            dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });

        currentIndex = index;
    }

    function goToNext() {
        var nextIndex = (currentIndex + 1) % slides.length;
        setActiveSlide(nextIndex);
    }

    function goToPrev() {
        var prevIndex = (currentIndex - 1 + slides.length) % slides.length;
        setActiveSlide(prevIndex);
    }

    function restartAutoplay() {
        if (timerId) {
            window.clearInterval(timerId);
        }
        timerId = window.setInterval(goToNext, autoplayMs);
    }

    slides.forEach(function (_, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'dot' + (index === 0 ? ' active' : '');
        dot.setAttribute('aria-label', 'Go to slide ' + (index + 1));
        dot.setAttribute('role', 'tab');
        dot.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
        dot.addEventListener('click', function () {
            setActiveSlide(index);
            restartAutoplay();
        });
        dotsContainer.appendChild(dot);
    });

    nextButton.addEventListener('click', function () {
        goToNext();
        restartAutoplay();
    });

    prevButton.addEventListener('click', function () {
        goToPrev();
        restartAutoplay();
    });

    slider.addEventListener('mouseenter', function () {
        if (timerId) {
            window.clearInterval(timerId);
        }
    });

    slider.addEventListener('mouseleave', function () {
        restartAutoplay();
    });

    restartAutoplay();
});
