(function () {
  function load(options) {
    var opts = options || {};
    var githubUser = opts.githubUser || "";
    if (!githubUser) {
      if (typeof opts.onFallback === "function") {
        opts.onFallback();
      }
      return;
    }

    var cacheKey = "velora_github_profile_" + githubUser;
    var cacheTtlMs =
      typeof opts.cacheTtlMs === "number" ? opts.cacheTtlMs : 10 * 60 * 1000;

    function getCached() {
      try {
        var raw = localStorage.getItem(cacheKey);
        if (!raw) {
          return null;
        }

        var parsed = JSON.parse(raw);
        if (!parsed || !parsed.savedAt || !parsed.data) {
          return null;
        }

        if (Date.now() - Number(parsed.savedAt) > cacheTtlMs) {
          return null;
        }

        return parsed.data;
      } catch (_) {
        return null;
      }
    }

    function setCached(data) {
      try {
        localStorage.setItem(
          cacheKey,
          JSON.stringify({
            savedAt: Date.now(),
            data: data,
          }),
        );
      } catch (_) {}
    }

    var cached = getCached();
    if (cached) {
      if (typeof opts.onData === "function") {
        opts.onData(cached);
      }
      return;
    }

    fetch("https://api.github.com/users/" + githubUser)
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to fetch profile");
        }
        return response.json();
      })
      .then(function (data) {
        if (typeof opts.onData === "function") {
          opts.onData(data);
        }
        setCached(data);
      })
      .catch(function () {
        if (typeof opts.onFallback === "function") {
          opts.onFallback();
        }
      });
  }

  window.VeloraGithubProfile = {
    load: load,
  };
})();
