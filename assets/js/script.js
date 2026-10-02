import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { SplitText } from "gsap/SplitText";

gsap.registerPlugin(ScrollTrigger, SplitText);
const mm = gsap.matchMedia();

const header = document.getElementById("site-header");
const toggle = document.getElementById("menu-toggle");
const menu = document.getElementById("main-menu");

//ANIMACION ENTRADA ->
const intro = document.getElementById("intro");
const introLogo = document.getElementById("intro-logo");
let startHero;
const heroGo = new Promise((resolve) => (startHero = resolve));

// Una vez por sesión: si ya se vio, se omite (try/catch: en modo privado el storage puede lanzar error)
let introSeen = false;
try {
  introSeen = sessionStorage.getItem("intro-seen") === "1";
  sessionStorage.setItem("intro-seen", "1");
} catch {}

// Sin foco ni clics en la página mientras dura la intro; el header además hasta que entra (data-ready)
const lockedByIntro = [...document.querySelectorAll("main, footer, #cookie-banner, a[href='#main']")];
header.inert = true;

if (introSeen) {
  intro.remove();
  startHero();
} else {
  //Bloqueo scroll (clase en <html>; el CSS bloquea el body para que la barra vertical no desaparezca)
  history.scrollRestoration = "manual";
  window.scrollTo(0, 0);
  document.documentElement.classList.add("is-locked");
  lockedByIntro.forEach((el) => (el.inert = true));

  gsap.set(introLogo, { scale: 0.01, opacity: 1 });

  const introTl = gsap.timeline({
    delay: 0.2,
    onComplete: () => {
      document.documentElement.classList.remove("is-locked");
      lockedByIntro.forEach((el) => (el.inert = false));
      intro.remove();
      ScrollTrigger.refresh();
    },
  });
  introTl
    .to(introLogo, { scale: 1, duration: 1, ease: "back.out(1.5)" })
    .to(intro, { yPercent: -100, duration: 1, ease: "power3.inOut" }, "+=0.3")
    .add(startHero, "-=0.5");
}

// Segunda señal: 0,1 s después de la primera. Header y CTA del hero entran juntos con este retraso.
const headerGo = heroGo.then(() => new Promise((resolve) => gsap.delayedCall(0.1, resolve)));
headerGo.then(() => {
  header.inert = false;
  header.setAttribute("data-ready", "");
});
//-----
//HERO ->

//HEADER ->
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
//-----

// PREPARACIÓ COMUNA (plugins, matchMedia i helper d'entrada de títols) ->

// ENTRADA DE TÍTOLS: una timeline per títol (lletres + descripció per línies opcional), per poder-la governar sencera.
// Convenció: la descripció d'una secció és l'element amb data-reveal="description".
const revealTitle = (title, { description, cta, titleAt = 0, titleDuration = 0.6, titleStagger, extras, scrollTrigger, startWhen } = {}) => {
  let split;
  // Sin señal: arranca ya. Con señal: espera a que se resuelva.
  let released = !startWhen;
  startWhen?.then(() => (released = true));

  // Esperem les fonts: si no, les línies es tallen amb la tipografia de reserva
  document.fonts.ready.then(() => {
    split = SplitText.create(description ? [title, description] : title, {
      type: "chars, lines",
      mask: "lines",
      autoSplit: true,
      onSplit: (self) => {
        const titleChars = self.chars.filter((char) => title.contains(char));
        const tl = gsap.timeline({ scrollTrigger, paused: !released });
        if (!released) startWhen.then(() => tl.play());

        // Etiqueta "title": els passos d'una secció hi poden arrencar alhora (extras)
        tl.addLabel("title", titleAt);

        tl.from(
          titleChars,
          {
            duration: titleDuration,
            opacity: 0,
            yPercent: 100,
            clipPath: "inset(0 0 100% 0)",
            ease: "power2.out",
            // Per defecte, durada total màxima d'1s encara que el títol sigui llarg; titleStagger la fixa a mà
            stagger: { amount: titleStagger ?? Math.min(titleChars.length * 0.03, 1) },
          },
          "title",
        );

        if (description) {
          const descriptionLines = self.lines.filter((line) => description.contains(line));
          tl.from(descriptionLines, { opacity: 0, yPercent: 100, duration: 0.8, ease: "power2.out", stagger: 0.12 }, "-=0.4");
        }

        if (cta) {
          tl.from(cta, { opacity: 0, y: 60, duration: 0.8, ease: "power2.out" }, "-=0.3");
        }

        // Passos propis de cada secció, afegits a la mateixa timeline
        extras?.(tl);

        return tl;
      },
    });
  });

  return () => split?.revert();
};

// HERO (s1) ->
const heroTitle = document.getElementById("s1-title");
const heroDescription = document.querySelector("#s1 [data-reveal=description]");
const heroCta = document.querySelector("#s1 [data-reveal=cta]");

mm.add("(prefers-reduced-motion: no-preference)", () => {
  // El CTA va aparte del timeline del título: entra a la vez que el header (headerGo)
  const ctaIn = gsap.from(heroCta, { opacity: 0, y: 60, duration: 1.8, ease: "power2.out", paused: true });
  headerGo.then(() => ctaIn.play());

  return revealTitle(heroTitle, { description: heroDescription, startWhen: heroGo });
});
//-----

// AMPLIACIÓ DE VIDEO SECTION: CONVERSA
const videoBox = document.querySelector("[data-video-expand]");

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

// CARRUSEL DE CATEGORIES
const carousel = document.querySelector("[data-carousel]");
const carouselList = carousel.querySelector("[data-carousel=list]");
const prevButton = carousel.querySelector("[data-carousel=prev]");
const nextButton = carousel.querySelector("[data-carousel=next]");

const getCarouselStep = () => {
  const firstCard = carouselList.firstElementChild;
  const gap = parseFloat(getComputedStyle(carouselList).columnGap);
  return firstCard.offsetWidth + gap;
};

const updateCarouselButtons = () => {
  const maxScrollLeft = carouselList.scrollWidth - carouselList.clientWidth;
  prevButton.disabled = carouselList.scrollLeft <= 0;
  nextButton.disabled = carouselList.scrollLeft >= maxScrollLeft - 1;
};

const scrollCarousel = (direction) => {
  carouselList.scrollBy({ left: direction * getCarouselStep(), behavior: "smooth" });
};

prevButton.addEventListener("click", () => scrollCarousel(-1));
nextButton.addEventListener("click", () => scrollCarousel(1));
carouselList.addEventListener("scroll", updateCarouselButtons);
window.addEventListener("resize", updateCarouselButtons);
updateCarouselButtons();

// ENTRADA DE CARDS DE TARIFES: cortina de baix cap a dalt amb stagger
const pricingCards = gsap.utils.toArray("#s7 article");

mm.add("(prefers-reduced-motion: no-preference)", () => {
  gsap.fromTo(
    pricingCards,
    { clipPath: "inset(100% 0% 0% 0%)" },
    {
      clipPath: "inset(0% 0% 0% 0%)",
      duration: 1,
      ease: "power3.out",
      stagger: 0.5,
      clearProps: "clipPath",
      scrollTrigger: {
        trigger: pricingCards[0].parentElement,
        start: "top 80%",
        toggleActions: "play none none reset",
      },
    },
  );
});

const sectionTitles = gsap.utils.toArray("main section h2");
const footerTitle = document.querySelector("footer [data-reveal=title]");

// Passos propis per secció (clau = id de la secció): s'afegeixen a la timeline del seu títol.
// titleAt: moment de la timeline on arrenca el títol (per defecte 0). build: afegeix els passos propis.
// splitDescription: la descripció (data-reveal=description) entra dividida per línies, com al hero.
const sectionReveals = {
  s2: {
    build: (tl) => {
      tl.from("#s2 [data-reveal=description]", { opacity: 0, y: 40, duration: 0.8, ease: "power2.out" }, "-=0.4");
      // "<": les píldores arranquen alhora que la descripció
      tl.from("#s2 [data-reveal=pill]", { opacity: 0, y: 40, duration: 0.8, ease: "power2.out", stagger: 0.15 }, "<");
    },
  },
  s4: {
    // La descripció surt abans del títol (0 -> 0.6s); el títol arrenca quan ja és mig visible
    titleAt: 0.4,
    build: (tl) => {
      tl.from("#s4 [data-reveal=description]", { opacity: 0, duration: 0.6, ease: "power2.out" }, 0);
      // "title": les cards pugen des de baix alhora que el títol
      tl.from("#s4 [data-reveal=card]", { opacity: 0, y: 160, duration: 1.3, ease: "power2.out", stagger: 0.4 }, "title");
    },
  },
  s5: {
    build: (tl) => {
      tl.from("#s5 [data-reveal=subtitle]", { opacity: 0, y: 30, duration: 0.7, ease: "power2.out" }, "-=0.4");
      // Les píldores dels passos entren pel lateral (esquerra), en escala
      tl.from("#s5 [data-reveal=pill]", { opacity: 0, x: -80, duration: 0.8, ease: "power2.out", stagger: 0.15 }, "-=0.3");
      // Descripció d'avall i píldora final: fade des de baix, una darrere l'altra
      tl.from(
        "#s5 [data-reveal=description], #s5 [data-reveal=cta]",
        { opacity: 0, y: 40, duration: 0.8, ease: "power2.out", stagger: 0.2 },
        "-=0.3",
      );
    },
  },
  s6: {
    build: (tl) => {
      // Aparició de les cards amb fade, en escala
      tl.from("#s6 [data-reveal=card]", { opacity: 0, duration: 0.7, ease: "power2.out", stagger: 0.3 }, "-=0.4");
    },
  },
  s7: {
    build: (tl) => {
      // Descripció: fade simple després del títol
      tl.from("#s7 [data-reveal=description]", { opacity: 0, duration: 0.8, ease: "power2.out" }, "-=0.4");
    },
  },
  s8: {
    build: (tl) => {
      // Descripció: fade up després del títol
      tl.from("#s8 [data-reveal=description]", { opacity: 0, y: 40, duration: 0.8, ease: "power2.out" }, "-=0.4");
      // Preguntes: fade up des de baix, stagger lent
      tl.from("#s8 [data-reveal=card]", { opacity: 0, y: 40, duration: 0.9, ease: "power2.out", stagger: 0.3 }, "-=0.4");
      // Icona de fons: només opacity (el transform el porta el CSS), alhora que el títol
      tl.from("#s8 [data-reveal=icon]", { opacity: 0, duration: 4, ease: "power2.out" }, "title");
    },
  },
  contacte: {
    // La descripció es divideix per línies i entra dins la mateixa timeline que el títol (com al hero)
    splitDescription: true,
    build: (tl) => {
      // Camps del formulari i botó: fade up des de baix, en escala, a la mateixa timeline
      tl.from("#contacte [data-reveal=field]", { opacity: 0, y: 40, duration: 0.8, ease: "power2.out", stagger: 0.15 }, "-=0.4");
    },
  },
};

mm.add("(prefers-reduced-motion: no-preference)", () => {
  const cleanups = [
    ...sectionTitles.map((title) => {
      const section = title.closest("section");
      const { titleAt, build, splitDescription } = sectionReveals[section.id] ?? {};

      return revealTitle(title, {
        titleAt,
        description: splitDescription ? section.querySelector("[data-reveal=description]") : undefined,
        extras: build,
        scrollTrigger: { trigger: title, start: "top 85%", toggleActions: "play none none reset" },
      });
    }),
    // Títol gran del footer: mateix efecte, més lent (lletres 1s, repartides en 1.2s)
    revealTitle(footerTitle, {
      titleDuration: 1,
      titleStagger: 1.2,
      // Elements del panel del footer: fade up amb stagger, dins la mateixa timeline (arrenquen amb les lletres)
      extras: (tl) => tl.from("footer [data-reveal=item]", { opacity: 0, y: 40, duration: 0.8, ease: "power2.out", stagger: 0.1 }, "title+=1"),
      scrollTrigger: { trigger: footerTitle, start: "top 90%", toggleActions: "play none none reset" },
    }),
  ];

  return () => cleanups.forEach((cleanup) => cleanup());
});
