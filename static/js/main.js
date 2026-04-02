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