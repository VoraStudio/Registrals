// Obre/tanca el menú: només canvia l'estat; l'animació la fa Tailwind amb group-data-open:
const header = document.getElementById("site-header");
const toggle = document.getElementById("menu-toggle");
const menu = document.getElementById("main-menu");

const setMenuOpen = (isOpen) => {
  header.toggleAttribute("data-open", isOpen);
  toggle.setAttribute("aria-expanded", String(isOpen));
  toggle.setAttribute("aria-label", isOpen ? "Tancar el menú" : "Obrir el menú");
};

toggle.addEventListener("click", () => {
  setMenuOpen(!header.hasAttribute("data-open"));
});

menu.addEventListener("click", (event) => {
  if (!event.target.closest("a")) return;
  setMenuOpen(false);
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape" || !header.hasAttribute("data-open")) return;
  setMenuOpen(false);
  toggle.focus();
});

// Cambia los colores del header al salir del hero (las clases group-data-past-hero: hacen la transición)
const hero = document.getElementById("s1");
const headerOffset = 80;

const heroObserver = new IntersectionObserver(
  ([entry]) => {
    header.toggleAttribute("data-past-hero", !entry.isIntersecting);
  },
  { rootMargin: `-${headerOffset}px 0px 0px 0px` },
);

heroObserver.observe(hero);

// AMPLLIACIÓ DE VIDEO SECTION: CONVERSA
gsap.registerPlugin(ScrollTrigger);
const videoBox = document.querySelector("[data-video-expand]");
const mm = gsap.matchMedia();

mm.add("(min-width: 768px) and (prefers-reduced-motion: no-preference)", () => {
  gsap.fromTo(
    videoBox,
    { width: "70%", borderRadius: 32 },
    {
      width: "100%",
      borderRadius: 0,
      ease: "none",
      scrollTrigger: {
        trigger: videoBox,
        start: "top 80%",
        end: "top 10%",
        scrub: true,
      },
    },
  );
});

// PIN CARDS
const seccioCards = document.getElementById("s5");
const cards = gsap.utils.toArray("#s5 article");
const pills = gsap.utils.toArray("#s5 ol a");

mm.add("(min-width: 768px) and (prefers-reduced-motion: no-preference)", () => {
  const tl = gsap.timeline({
    defaults: { ease: "none" },
    scrollTrigger: {
      trigger: seccioCards,
      start: "top top",
      end: "+=400%",
      pin: true,
      scrub: true,
      onUpdate: (self) => setActivePill(Math.min(cards.length - 1, Math.floor(self.progress * cards.length))),
    },
  });

  const setActivePill = (index) => {
    pills.forEach((pill, i) => {
      if (i === index) pill.setAttribute("aria-current", "step");
      else pill.removeAttribute("aria-current");
    });
  };
  setActivePill(0);

  cards.forEach((card, i) => {
    tl.fromTo(card, { y: "100vh" }, { y: 0, duration: 1 });
    /*cards.slice(0, i).forEach((previous, j) => {
      const depth = i - j;
      tl.to(previous, { scale: 1 - 0.05 * depth, y: -18 * depth, duration: 1 }, "<");
    });*/
  });
});
