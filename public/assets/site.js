'use strict';
const menu = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
function closeMenu() {
  navigation.classList.remove('is-open');
  menu.setAttribute('aria-expanded', 'false');
}
menu.addEventListener('click', () => {
  const open = menu.getAttribute('aria-expanded') !== 'true';
  menu.setAttribute('aria-expanded', String(open));
  navigation.classList.toggle('is-open', open);
});
navigation.addEventListener('click', event => {
  if (event.target.closest('a')) closeMenu();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true') {
    closeMenu();
    menu.focus();
  }
});
document.querySelector('#year').textContent = new Date().getFullYear();
const inquiryPlan = document.querySelector('#inquiry-plan');
document.querySelectorAll('[data-plan]').forEach(link => {
  link.addEventListener('click', () => {
    inquiryPlan.value = link.dataset.plan;
    document.querySelector('#form-status').textContent = link.dataset.plan + ' selected. Complete your inquiry below.';
  });
});
document.querySelector('#inquiry-form').addEventListener('submit', event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const content = ['Booking Software — inquiry draft', '', 'Name: ' + data.get('name'), 'Business: ' + data.get('business'), 'Email: ' + data.get('email'), 'Rental type: ' + data.get('industry'), 'Interested plan: ' + data.get('plan'), '', String(data.get('message') || ''), '', 'Local draft only. This inquiry has not been sent.'].join('\n');
  const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = 'booking-inquiry.txt';
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
  document.querySelector('#form-status').textContent = 'Your inquiry draft is ready to download. Nothing has been sent or saved to a server.';
});
