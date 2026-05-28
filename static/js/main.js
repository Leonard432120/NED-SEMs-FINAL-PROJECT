// Simple fade-in animation
window.addEventListener("scroll", () => {
    document.querySelectorAll(".card").forEach(card => {
        const top = card.getBoundingClientRect().top;
        if (top < window.innerHeight - 50) {
            card.style.opacity = 1;
            card.style.transform = "translateY(0)";
        }
    });
});

function toggleMenu(menuId) {
    const menu = document.getElementById(menuId);
    if (!menu) return;
    menu.classList.toggle('open');
    const trigger = menu.previousElementSibling;
    if (trigger && trigger.tagName === 'SPAN') {
        trigger.classList.toggle('open');
    }
}

window.toggleMenu = toggleMenu;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sidebar-group > span').forEach(span => {
        span.style.cursor = 'pointer';
        span.addEventListener('click', () => {
            const menuId = span.getAttribute('data-menu');
            if (menuId) toggleMenu(menuId);
        });
    });
});