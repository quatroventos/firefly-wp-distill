(function () {
  const rest = (window.ffdAdmin && window.ffdAdmin.rest) || "";
  const nonce = (window.ffdAdmin && window.ffdAdmin.nonce) || "";

  function bindSearch(input) {
    const hidden = input.parentElement.querySelector('input[type="hidden"]');
    const box = input.parentElement.querySelector(".ffd-post-results");
    if (!hidden || !box) return;
    let t = null;
    input.addEventListener("input", function () {
      window.clearTimeout(t);
      t = window.setTimeout(async function () {
        const q = input.value.trim();
        if (q.length < 2) {
          box.innerHTML = "";
          return;
        }
        const res = await fetch(rest + "/hosts/search-posts?q=" + encodeURIComponent(q), {
          headers: { "X-WP-Nonce": nonce },
        });
        const items = await res.json();
        box.innerHTML = "";
        (items || []).forEach(function (item) {
          const b = document.createElement("button");
          b.type = "button";
          b.className = "button-link";
          b.textContent = item.title + " (#" + item.id + ")";
          b.addEventListener("click", function () {
            hidden.value = item.id;
            input.value = item.title;
            box.innerHTML = "";
          });
          box.appendChild(b);
        });
      }, 250);
    });
  }

  document.querySelectorAll(".ffd-post-search").forEach(bindSearch);

  const add = document.querySelector(".ffd-add-route");
  const routes = document.querySelector(".ffd-routes");
  if (add && routes) {
    add.addEventListener("click", function () {
      const wrap = document.createElement("div");
      wrap.className = "ffd-route";
      wrap.innerHTML =
        '<input name="route_path[]" placeholder="/obrigado/" />' +
        '<input class="ffd-post-search" name="route_post_title[]" placeholder="buscar post" />' +
        '<input type="hidden" name="route_post_id[]" value="" />' +
        '<label><input type="checkbox" name="route_in_sitemap[]" value="1" checked /> sitemap</label>' +
        '<div class="ffd-post-results"></div>';
      routes.appendChild(wrap);
      bindSearch(wrap.querySelector(".ffd-post-search"));
    });
  }
})();
