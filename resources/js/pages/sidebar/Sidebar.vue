<template>
  <div>
    <nav>
      <ul class="nav">
        <li v-if="!requestIs('/search*')" class="nav-item">
          <form method="GET" action="/search">
            <div class="input-group">
              <input
                type="text"
                class="form-control text-white"
                name="query"
                placeholder="Search"
                aria-label="Search"
              />
              <div class="input-group-append">
                <button class="btn btn-primary" type="button">
                  <i class="fa fa-search" />
                </button>
              </div>
            </div>
          </form>
        </li>
        <!-- <li class="nav-item">
          <a
            class="nav-link"
            href="/dashboard"
          >
            <i class="fa fa-dashboard" /> Call Center
          </a>
        </li>
        <li class="nav-item">
          <a
            class="nav-link"
            href="/sales_dashboard"
          >
            <i class="fa fa-dashboard" /> Sales
          </a>
        </li>
        <li class="nav-title">
          Pages
        </li> -->
      </ul>
    </nav>
    <div v-if="!dataIsLoaded" class="animated yt-loader" />
    <nav class="sidebar-nav" />
  </div>
</template>
<script>
import { arrayToTree } from "utils/arrayManipulation";
import { mapState } from "vuex";
import { requestIs } from "utils/urlHelpers";

export default {
  name: "Sidebar",
  data() {
    return {
      dataIsLoaded: false,
      menu: JSON.parse(localStorage.getItem("sidebarMenu")) || [],
      requestIs,
      currentSubMenu: null,
    };
  },
  computed: {
    ...mapState({
      roleId: (state) =>
        state.user !== null && state.user !== undefined
          ? state.user.role_id
          : 1,
    }),
  },
  mounted() {
    // eslint-disable-next-line prefer-const
    const menuContainer = document.querySelector(".sidebar-nav");
    menuContainer.onclick = (e) => this.toggleSubMenu(e.target);

    if (this.menu.length) {
      this.generateMenu(this.menu, menuContainer);
    }

    axios
      .get("/menus/get_sidebar_menu")
      .then((res) => {
        this.menu = arrayToTree(res.data);
        this.sortByPosition(this.menu);
        // Only update menu if the cache info is different
        if (JSON.stringify(this.menu) !== localStorage.getItem("sidebarMenu")) {
          localStorage.setItem("sidebarMenu", JSON.stringify(this.menu));
          menuContainer.innerHTML = "";
          this.generateMenu(this.menu, menuContainer);
        }
      })
      .catch(console.log);
  },
  methods: {
    sortByPosition(arr) {
      const _sort = (arrs) => arrs.sort((a, b) => a.position - b.position);
      const searchAndSort = (arrsas) =>
        arrsas.forEach((elemt) => {
          if (elemt.children.length) {
            elemt.children = _sort(elemt.children);
            searchAndSort(elemt.children);
          }
          _sort(arrsas);
        });
      searchAndSort(arr);
    },
    generateMenu(menuArr, container) {
      const menuHTML = document.createElement("UL");
      container.appendChild(menuHTML);
      menuHTML.classList.add(
        this.isSubMenu(container) ? "nav-dropdown-items" : "nav"
      );

      menuArr.forEach((m) => {
        if (
          m.role_permissions &&
          !m.role_permissions
            .split(",")
            .includes(this.roleId ? this.roleId.toString() : "1")
        ) {
          return;
        }

        const li = document.createElement("LI");
        menuHTML.appendChild(li);
        li.classList.add("nav-item");

        const button = document.createElement(m.children.length ? "SPAN" : "A");
        button.classList.add("nav-link");
        li.appendChild(button);

        const label = document.createTextNode(m.name);
        button.appendChild(label);

        if (m.icon) {
          const i = document.createElement("I");
          button.insertBefore(i, label);
          i.className = `fa ${m.icon} mr-1`;
        }

        if (m.children.length) {
          li.classList.add("w-dropdown");
          button.classList.add("nav-dropdown-toggle");
          this.generateMenu(m.children, li);
        } else if (m.url) {
          button.setAttribute("href", m.url);

          if (this.isCurrentPath(m.url)) {
            this.showSubMenu(container);
            button.classList.add("active");
          }
        }
      });

      this.dataIsLoaded = true;
    },
    toggleSubMenu(elemt) {
      if (this.isSubMenuToggle(elemt)) {
        const subMenuElemt = elemt.parentNode;

        if (this.subMenuIsOpen(subMenuElemt)) {
          return this.hideSubMenu(subMenuElemt);
        }
        return this.showSubMenu(subMenuElemt);
      }
    },
    showSubMenu(elemt) {
      if (this.isSubMenu(elemt)) {
        elemt.classList.add("nav-dropdown");
        elemt.classList.add("open");
        elemt.childNodes[0].classList.add("active");
        elemt.childNodes[1].style.display = "block";
        elemt.childNodes[1].style.position = "static";

        if (this.currentSubMenu) {
          this.hideSubMenu(this.currentSubMenu);
        }
        this.currentSubMenu = elemt;
      }
    },
    hideSubMenu(elemt) {
      if (this.isSubMenu(elemt)) {
        elemt.classList.remove("nav-dropdown");
        elemt.classList.remove("open");
        elemt.childNodes[0].classList.remove("active");
        elemt.childNodes[1].style.display = "none";
        this.currentSubMenu = null;
      }
    },
    isCurrentPath(path) {
      return window.location.pathname.includes(path);
    },
    isSubMenu(elemt) {
      return (
        elemt &&
        elemt.tagName === "LI" &&
        elemt.classList.contains("w-dropdown")
      );
    },
    isSubMenuToggle(elemt) {
      return elemt && elemt.classList.contains("nav-dropdown-toggle");
    },
    subMenuIsOpen(elemt) {
      return (
        elemt.classList.contains("nav-dropdown") &&
        elemt.classList.contains("open")
      );
    },
  },
};
</script>
<style scoped>
.sidebar .nav .nav-item ul {
  overflow-y: visible !important;
}

.nav > li:first-child > form > .input-group {
  margin-bottom: 4px;
}

.animated {
  -webkit-animation-duration: 2s;
  animation-duration: 2s;
  animation-iteration-count: infinite;
  -moz-user-select: none;
  -ms-user-select: none;
  -webkit-user-select: none;
}

.yt-loader {
  -webkit-animation-name: horizontalProgressBar;
  animation-name: horizontalProgressBar;
  -webkit-animation-timing-function: ease;
  animation-timing-function: ease;
  background: #20a8d8;
  height: 3px;
  left: 0;
  top: 0;
  width: 0%;
  z-index: 9999;
  position: relative;
}

.yt-loader:after {
  display: block;
  position: absolute;
  content: "";
  right: 0px;
  width: 100px;
  height: 100%;
  box-shadow: #4ea6ef 1px 0 6px 1px;
  opacity: 0.5;
}

@keyframes horizontalProgressBar {
  0% {
    width: 0%;
  }
  20% {
    width: 10%;
  }
  30% {
    width: 15%;
  }
  40% {
    width: 18%;
  }
  50% {
    width: 20%;
  }
  60% {
    width: 22%;
  }
  100% {
    width: 100%;
  }
}
</style>
