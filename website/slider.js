document.addEventListener('DOMContentLoaded', () => {
  const slides = Array.from(document.querySelectorAll('.slide'));
  const dots = Array.from(document.querySelectorAll('.dot'));
  const prevButton = document.querySelector('.slider-control.prev');
  const nextButton = document.querySelector('.slider-control.next');

  if (!slides.length || !dots.length) return;

  let currentIndex = 0;

  function updateSlider(index) {
    slides.forEach((slide, slideIndex) => {
      slide.classList.toggle('active', slideIndex === index);
    });

    dots.forEach((dot, dotIndex) => {
      dot.classList.toggle('active', dotIndex === index);
    });
  }

  function showNextSlide() {
    currentIndex = (currentIndex + 1) % slides.length;
    updateSlider(currentIndex);
  }

  function showPrevSlide() {
    currentIndex = (currentIndex - 1 + slides.length) % slides.length;
    updateSlider(currentIndex);
  }

  prevButton?.addEventListener('click', showPrevSlide);
  nextButton?.addEventListener('click', showNextSlide);

  dots.forEach(dot => {
    dot.addEventListener('click', () => {
      currentIndex = Number(dot.dataset.slide);
      updateSlider(currentIndex);
    });
  });

  setInterval(showNextSlide, 5000);
  updateSlider(currentIndex);
});
