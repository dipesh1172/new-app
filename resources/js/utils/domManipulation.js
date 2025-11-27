export const offset = (el) => {
  const rect = el.getBoundingClientRect(),
      scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
      scrollTop = window.pageYOffset || document.documentElement.scrollTop;
  return { top: rect.top + scrollTop, left: rect.left + scrollLeft };
};

export const replaceFilterBar = (breadcrumbClass, filterbarClass, filterbarReplacedClass) => () => {
  const breadcrumb = document.getElementsByClassName(breadcrumbClass)[0];
  const appheader = document.getElementsByClassName('app-header')[0];
  const scrollTop = window.pageYOffset || (document.documentElement || document.body.parentNode || document.body).scrollTop;
  const filterbar = document.getElementsByClassName(filterbarClass)[0];
  if (filterbar && breadcrumb) {
      if (scrollTop > (offset(breadcrumb).top - appheader.offsetHeight)) {
          filterbar.classList.add(filterbarReplacedClass);
      }
      else {
          filterbar.classList.remove(filterbarReplacedClass);
      }
  }
};
