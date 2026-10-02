// Banner de cookies: guarda la decisión del visitante y la puede reabrir desde el footer
const CONSENT_KEY = "registrals-cookie-consent";
const banner = document.getElementById("cookie-banner");

const getConsent = () => {
  try {
    return localStorage.getItem(CONSENT_KEY);
  } catch {
    return null;
  }
};

const saveConsent = (value) => {
  try {
    localStorage.setItem(CONSENT_KEY, value);
  } catch {
    // Sin almacenamiento (modo privado): la elección solo vale para esta visita
  }
};

const setBannerVisible = (isVisible) => {
  banner.hidden = !isVisible;
};

document.addEventListener("click", (event) => {
  const action = event.target.closest("[data-cookie]")?.dataset.cookie;
  if (!action) return;

  if (action === "settings") {
    setBannerVisible(true);
    banner.querySelector("[data-cookie=accept]").focus();
    return;
  }

  saveConsent(action === "accept" ? "accepted" : "rejected");
  setBannerVisible(false);
});

if (!getConsent()) setBannerVisible(true);
