document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('click', () => {
        card.style.transform = "scale(0.98)";
        setTimeout(() => {
            card.style.transform = "";
        }, 150);
    });
});