// node_modules/vanilla-cookieconsent/dist/cookieconsent.mjs
var e = "opt-in", t = "opt-out", n = "show--consent", r = "show--preferences", i = "disable--interaction", a = "data-category", o = "button", s = "aria-hidden", c = "btn-group", l = "click", u = "data-role", d = "consentModal", f = "preferencesModal", p = class {
	constructor() {
		this.t = {
			mode: e,
			revision: 0,
			autoShow: !0,
			lazyHtmlGeneration: !0,
			autoClearCookies: !0,
			manageScriptTags: !0,
			hideFromBots: !0,
			respectGpc: !1,
			cookie: {
				name: "cc_cookie",
				expiresAfterDays: 182,
				domain: "",
				path: "/",
				secure: !0,
				sameSite: "Lax"
			}
		}, this.o = {
			i: {},
			l: "",
			_: {},
			u: {},
			p: {},
			m: [],
			v: !1,
			h: null,
			C: null,
			S: null,
			M: "",
			k: !0,
			D: !1,
			T: !1,
			N: !1,
			A: !1,
			H: [],
			j: !1,
			I: !0,
			P: [],
			L: !1,
			V: "",
			F: !1,
			O: [],
			R: [],
			B: [],
			$: [],
			G: !1,
			U: !1,
			J: !1,
			q: [],
			K: [],
			W: [],
			X: {},
			Y: {},
			Z: {},
			ee: {},
			te: {},
			oe: []
		}, this.ne = {
			ae: {},
			se: {}
		}, this.ce = {}, this.re = {
			ie: "cc:onFirstConsent",
			le: "cc:onConsent",
			de: "cc:onChange",
			_e: "cc:onModalShow",
			fe: "cc:onModalHide",
			ue: "cc:onModalReady"
		};
	}
}, m = new p(), h = (e, t) => e.indexOf(t), g = (e, t) => h(e, t) !== -1, _ = (e) => Object.keys(e), v = (e) => Array.from(new Set(e)), y = (e) => {
	if (typeof e != "object" || !e) return e;
	if (e instanceof Date) return new Date(e.getTime());
	let t = Array.isArray(e) ? [] : {};
	for (let n in e) {
		let r = e[n];
		t[n] = y(r);
	}
	return t;
}, b = (e, t) => {
	let n = e || [], r = t || [];
	return n.filter((e) => !g(r, e)).concat(r.filter((e) => !g(n, e)));
}, x = () => document.activeElement, S = (e) => e.preventDefault(), C = (e, t) => e.querySelectorAll(t), w = (e) => e.dispatchEvent(new Event("change")), T = (e, t) => {
	let n = document.createElement(e);
	return e === o && (n.type = e), t && k(n, t), n;
}, E = (e, t, n) => e.setAttribute(t, n), D = (e, t, n) => {
	e.removeAttribute(n ? "data-" + t : t);
}, ee = (e, t, n) => e.getAttribute(n ? "data-" + t : t), O = (e, t) => e.appendChild(t), k = (e, t) => e.classList.add(t), A = (e, t) => k(e, "cm__" + t), j = (e, t) => k(e, "pm__" + t), te = (e, t) => e.classList.remove(t), M = (e, t, n, r) => {
	e.addEventListener(t, n), r && m.o.m.push({
		pe: e,
		me: t,
		ge: n
	});
}, ne = (e, t, n) => {
	e && (t && (e.tabIndex = -1), e.focus({ preventScroll: n }), t && e.removeAttribute("tabindex"));
}, re = () => {
	let { be: e, ve: t } = m.ne;
	return [.../* @__PURE__ */ new Set([e, t])].filter((e) => e);
}, ie = (e, t, n = re()) => {
	for (let r of n) r.classList.toggle(e, !!t);
}, ae, oe = (e) => {
	clearTimeout(ae);
	let t = re();
	e ? ie(i, !0, t) : ae = setTimeout(() => {
		ie(i, !1, t);
	}, 500);
}, se = (e) => Array.isArray(e), ce = (e) => typeof e == "string", le = (e) => !!e && typeof e == "object" && !se(e), ue = (e) => typeof e == "function", de = () => {
	let e = m.ne.ae;
	if (!e) return [];
	let t = [];
	for (let n in e) e[n].checked && t.push(n);
	return t;
}, fe = () => m.t.respectGpc && m.o.k && !0 === navigator.globalPrivacyControl, pe = (e, t, n) => {
	let r = m.o;
	return !!r.F[e].readOnly || (n ? t ? g(r.Y[e], t) : g(r.R, e) : g(r.$, e));
}, me = (e) => {
	let { ae: t, se: n } = m.ne;
	for (let r in t) {
		let i = [];
		t[r].checked = pe(r, "", e);
		for (let t in n[r]) {
			let a = n[r][t];
			a.checked = pe(r, t, e), a.checked && i.push(t);
		}
		m.o.Z[r] = i;
	}
}, he = (e) => {
	m.o.R = v(e), m.o.V = (() => {
		let e = "custom", { R: t, O: n, B: r } = m.o, i = t.length;
		return i === n.length ? e = "all" : i === r.length && (e = "necessary"), e;
	})();
}, ge = (e, t) => dispatchEvent(new CustomEvent(e, { detail: t })), _e = (e, t, n) => {
	let { de: r, le: i, ie: a, fe: o, ue: s, _e: c } = m.ce, l = m.re;
	if (t) {
		let r = { modalName: t };
		return e === l._e ? ue(c) && c(r) : e === l.fe ? ue(o) && o(r) : (r.modal = n, ue(s) && s(r)), ge(e, r);
	}
	let u = { cookie: m.o.p };
	e === l.ie ? ue(a) && a(y(u)) : e === l.le ? ue(i) && i(y(u)) : (u.changedCategories = m.o.P, u.changedServices = m.o.ee, ue(r) && r(y(u))), ge(e, y(u));
}, ve = () => {
	let { R: e, Y: t, ee: n, O: r, X: i, oe: o, P: s } = m.o;
	for (let e of r) {
		let r = n[e], a = r && r.length > 0 ? r : t[e] || [];
		for (let n of a) {
			let r = i[e][n];
			if (!r) continue;
			let { onAccept: a, onReject: o } = r;
			!r.he && g(t[e], n) ? (r.he = !0, ue(a) && a()) : r.he && !g(t[e], n) && (r.he = !1, ue(o) && o());
		}
	}
	if (!m.t.manageScriptTags) return;
	let c = (r, i) => {
		if (i >= r.length) return;
		let l = o[i];
		if (l.ye) return c(r, i + 1);
		let u = l.Ce, d = l.we, f = l.Se, p = g(e, d), m = !!f && g(t[d], f);
		if (!f && !l.xe && p || !f && l.xe && !p && g(s, d) || f && !l.xe && m || f && l.xe && !m && g(n[d] || [], f)) {
			l.ye = !0;
			let e = ee(u, "type", !0);
			D(u, "type", !!e), D(u, a);
			let t = ee(u, "src", !0);
			t && D(u, "src", !0);
			let n = T("script");
			n.textContent = u.innerHTML;
			for (let { nodeName: e } of u.attributes) E(n, e, u[e] || ee(u, e));
			e && (n.type = e), t ? n.src = t : t = u.src;
			let o = !!t && (!e || ["text/javascript", "module"].includes(e));
			if (o && (n.onload = n.onerror = () => {
				c(r, ++i);
			}), u.replaceWith(n), o) return;
		}
		c(r, ++i);
	};
	c(o, 0);
}, ye = (e, t, n, r) => {
	let i = "accept-", { show: a, showPreferences: o, hide: s, hidePreferences: c, acceptCategory: u } = t, d = e || document, f = (e) => C(d, `[data-cc="${e}"]`), p = (e, t) => {
		S(e), u(t), c(), s();
	}, h = f("show-preferencesModal"), g = f("show-consentModal"), _ = f(i + "all"), v = f(i + "necessary"), y = f(i + "custom"), b = m.t.lazyHtmlGeneration;
	for (let e of h) E(e, "aria-haspopup", "dialog"), M(e, l, (e) => {
		S(e), o();
	}, !0), b && (M(e, "mouseenter", (e) => {
		S(e), m.o.A || n(t, r);
	}, !0), M(e, "focus", () => {
		m.o.A || n(t, r);
	}, !0));
	for (let e of g) E(e, "aria-haspopup", "dialog"), M(e, l, (e) => {
		S(e), a(!0);
	}, !0);
	for (let e of _) M(e, l, (e) => {
		p(e, "all");
	}, !0);
	for (let e of y) M(e, l, (e) => {
		p(e);
	}, !0);
	for (let e of v) M(e, l, (e) => {
		p(e, []);
	}, !0);
}, be = (e) => {
	let t = m.ne, n = m.o;
	((e) => {
		let r = e === t.Me;
		M(n.i.disablePageInteraction ? t.be : r ? t.ke : t.be, "keydown", (i) => {
			if (i.key !== "Tab" || !(r ? n.T && !n.N : n.N)) return;
			let a = x(), o = r ? n.q : n.K;
			if (o.length !== 0) {
				if (i.shiftKey) {
					let n = r ? t.De : t.Te;
					a !== o[0] && a !== n && e.contains(a) || (S(i), ne(o[1]));
				} else a !== o[1] && e.contains(a) || (S(i), ne(o[0]));
			}
		}, !0);
	})(e);
}, xe = [
	"[href]",
	o,
	"input",
	"details",
	"[tabindex]"
].map((e) => e + ":not([tabindex=\"-1\"])").join(","), Se = (e) => {
	let { o: t, ne: n } = m, r = (e, t) => {
		let n = C(e, xe);
		t[0] = n[0], t[1] = n[n.length - 1];
	};
	e === 1 && t.D && r(n.Me, t.q), e === 2 && t.A && r(n.Ee, t.K);
}, Ce = [
	"M 19.5 4.5 L 4.5 19.5 M 4.5 4.501 L 19.5 19.5",
	"M 3.572 13.406 L 8.281 18.115 L 20.428 5.885",
	"M 21.999 6.94 L 11.639 17.18 L 2.001 6.82 "
], we = (e = 0, t = 1.5) => `<svg aria-hidden="true" viewBox="0 0 24 24" stroke-width="${t}"><path d="${Ce[e]}"/></svg>`, Te = "bottom", Ee = "left", De = "center", Oe = "right", ke = "inline", Ae = "wide", je = "pm--", Me = [
	"middle",
	"top",
	Te
], Ne = [
	Ee,
	De,
	Oe
], Pe = {
	box: {
		Ne: [Ae, ke],
		Ae: Me,
		He: Ne,
		je: Te,
		Ie: Oe
	},
	cloud: {
		Ne: [ke],
		Ae: Me,
		He: Ne,
		je: Te,
		Ie: De
	},
	bar: {
		Ne: [ke],
		Ae: Me.slice(1),
		He: [],
		je: Te,
		Ie: ""
	}
}, Fe = {
	box: {
		Ne: [],
		Ae: [],
		He: [],
		je: "",
		Ie: ""
	},
	bar: {
		Ne: [Ae],
		Ae: [],
		He: [Ee, Oe],
		je: "",
		Ie: Ee
	}
}, Ie = (e) => {
	let t = m.o.i.guiOptions, n = t && t.consentModal, r = t && t.preferencesModal;
	e === 0 && Le(m.ne.Me, Pe, n, "cm--", "box", "cm"), e === 1 && Le(m.ne.Ee, Fe, r, je, "box", "pm");
}, Le = (e, t, n, r, i, a) => {
	e.className = a;
	let o = n && n.layout, s = n && n.position, c = n && n.flipButtons, l = !n || !1 !== n.equalWeightButtons, u = o && o.split(" ") || [], d = u[0], f = u[1], p = d in t ? d : i, h = t[p], _ = g(h.Ne, f) && f, v = s && s.split(" ") || [], y = v[0], b = r === je ? v[0] : v[1], x = g(h.Ae, y) ? y : h.je, S = g(h.He, b) ? b : h.Ie, C = (t) => {
		t && k(e, r + t);
	};
	C(p), C(_), C(x), C(S), c && C("flip");
	let w = a + "__btn--secondary";
	if (a === "cm") {
		let { Pe: e, Le: t } = m.ne;
		e && (l ? te(e, w) : k(e, w)), t && (l ? te(t, w) : k(t, w));
	} else {
		let { Ve: e } = m.ne;
		e && (l ? te(e, w) : k(e, w));
	}
}, Re = (e, t) => {
	let n = m.o, r = m.ne, { hide: i, hidePreferences: a, acceptCategory: d } = e, p = (e) => {
		d(e), a(), i();
	}, h = n.u && n.u.preferencesModal;
	if (!h) return;
	let g = h.title, v = h.closeIconLabel, y = h.acceptAllBtn, b = h.acceptNecessaryBtn, x = h.savePreferencesBtn, S = h.sections || [], C = y || b || x;
	if (r.Fe) r.Oe = T("div", "pm__body");
	else {
		r.Fe = T("div", "pm-wrapper");
		let e = T("div", "pm-overlay");
		O(r.Fe, e), M(e, l, a), r.Ee = T("div", "pm"), E(r.Ee, "role", "dialog"), E(r.Ee, s, !0), E(r.Ee, "aria-modal", !0), E(r.Ee, "aria-labelledby", "pm__title"), M(r.be, "keydown", (e) => {
			e.key === "Escape" && a();
		}, !0), r.Re = T("div", "pm__header"), r.Be = T("h2", "pm__title"), r.Be.id = "pm__title", r.$e = T(o, "pm__close-btn"), E(r.$e, "aria-label", h.closeIconLabel || ""), M(r.$e, l, a), r.Ge = T("span"), r.Ge.innerHTML = we(), O(r.$e, r.Ge), r.Ue = T("div", "pm__body"), r.Je = T("div", "pm__footer"), r.ze = T("div"), r.qe = T("div"), j(r.ze, c), j(r.qe, c), O(r.Je, r.ze), O(r.Je, r.qe), O(r.Re, r.Be), O(r.Re, r.$e), r.Te = r.Be, E(r.Te, "tabIndex", -1), O(r.Ee, r.Re), O(r.Ee, r.Ue), O(r.Fe, r.Ee);
	}
	let w;
	g && (r.Be.innerHTML = g, v && E(r.$e, "aria-label", v)), S.forEach((e, t) => {
		let i = e.title, a = e.description, c = e.linkedCategory, l = c && n.F[c], u = e.cookieTable, d = u && u.body, f = d && d.length > 0, p = !!l, m = p && n.X[c], g = le(m) && _(m) || [], v = p && !!i && (!!a || !!f || _(m).length > 0), y = g.length, b = c + "-desc", x = T("div", "pm__section"), S, C;
		if ((v || a) && (S = T("div", "pm__section-desc-wrapper")), v && y > 0 && O(S, ze(m, g, l, c)), i) {
			let e = T("div");
			if (C = T(p ? o : "div"), j(e, "section-title-wrapper"), j(C, "section-title"), C.innerHTML = i, O(e, C), p) {
				x.className += "--toggle";
				let t = We(i, c, l), n = h.serviceCounterLabel;
				if (y > 0 && ce(n) && O(C, Be(y, n)), v) {
					let t = T("span");
					t.innerHTML = we(2, 3.5), j(t, "section-arrow"), O(e, t), j(x, "section--expandable"), E(C, "aria-expanded", !1), E(C, "aria-controls", b);
				}
				O(e, t);
			} else E(C, "role", "heading"), E(C, "aria-level", "3");
			O(x, e);
		}
		if (a) {
			let e = T("p", "pm__section-desc");
			e.innerHTML = a, O(S, e);
		}
		v && (E(S, s, "true"), S.id = b, Ve(C, x, S), f && O(S, He(u, t))), (v || a) && O(x, S);
		let D = r.Oe || r.Ue;
		p ? (w ||= T("div", "pm__section-toggles"), w.appendChild(x)) : w = null, O(D, w || x);
	}), y && (r.Ke || (r.Ke = T(o, "pm__btn"), E(r.Ke, u, "all"), O(r.ze, r.Ke), M(r.Ke, l, () => p("all"))), r.Ke.innerHTML = y), b && (r.Ve || (r.Ve = T(o, "pm__btn"), E(r.Ve, u, "necessary"), O(r.ze, r.Ve), M(r.Ve, l, () => p([]))), r.Ve.innerHTML = b), x && (r.Qe || (r.Qe = T(o, "pm__btn"), j(r.Qe, "btn--secondary"), E(r.Qe, u, "save"), O(r.qe, r.Qe), M(r.Qe, l, () => p())), r.Qe.innerHTML = x), r.Oe && (r.Ee.replaceChild(r.Oe, r.Ue), r.Ue = r.Oe), C && O(r.Ee, r.Je), Ie(1), n.A || (n.A = !0, _e(m.re.ue, f, r.Ee), t(), O(r.ke, r.Fe), be(r.Ee), setTimeout(() => k(r.Fe, "cc--anim"), 100)), Se(2);
}, ze = (e, t, n, r) => {
	let i = T("div", "pm__section-services");
	for (let a of t) {
		let t = e[a], o = t && t.label || a, s = T("div"), c = T("div"), l = T("div"), u = T("div");
		j(s, "service"), j(u, "service-title"), j(c, "service-header"), j(l, "service-icon");
		let d = We(o, a, n, !0, r);
		u.innerHTML = o, O(c, l), O(c, u), O(s, c), O(s, d), O(i, s);
	}
	return i;
}, Be = (e, t) => {
	let n = T("span", "pm__badge");
	if (j(n, "service-counter"), E(n, s, !0), E(n, "data-servicecounter", e), t) {
		let r = t.split("|");
		t = r.length > 1 && e > 1 ? r[1] : r[0], E(n, "data-counterlabel", t);
	}
	return n.innerHTML = e + (t ? " " + t : ""), n;
}, Ve = (e, t, n) => {
	M(e, l, () => {
		t.classList.contains("is-expanded") ? (te(t, "is-expanded"), E(e, "aria-expanded", "false"), E(n, s, "true")) : (k(t, "is-expanded"), E(e, "aria-expanded", "true"), D(n, s));
	});
}, He = (e, t) => {
	let n = T("table"), r = T("thead"), i = T("tbody"), a = e.caption;
	if (a) {
		let e = T("caption", "pm__table-caption");
		e.innerHTML = a, n.appendChild(e);
	}
	j(n, "section-table"), j(r, "table-head"), j(i, "table-body");
	let o = e.headers, s = _(o), c = m.ne.We.createDocumentFragment(), l = T("tr");
	for (let e of s) {
		let n = o[e], r = T("th");
		r.id = "cc__row-" + e + t, E(r, "scope", "col"), j(r, "table-th"), r.innerHTML = n, O(c, r);
	}
	O(l, c), O(r, l);
	let u = m.ne.We.createDocumentFragment();
	for (let n of e.body) {
		let e = T("tr", "pm__table-tr");
		for (let r of s) {
			let i = o[r], a = n[r], s = T("td"), c = T("div");
			j(s, "table-td"), E(s, "data-column", i), E(s, "headers", "cc__row-" + r + t), c.insertAdjacentHTML("beforeend", a), O(s, c), O(e, s);
		}
		O(u, e);
	}
	return O(i, u), O(n, r), O(n, i), n;
}, Ue = (e) => {
	let t = T("template");
	t.innerHTML = e;
	let n = "";
	for (let e of t.content.childNodes) e.nodeType === Node.TEXT_NODE && (n += e.textContent);
	return n;
};
function We(e, t, n, r, i) {
	let o = m.o, c = m.ne, u = T("label"), d = T("input"), f = T("span"), p = T("span"), h = T("span"), g = T("span"), _ = T("span");
	return g.innerHTML = we(1, 3), _.innerHTML = we(0, 3), d.type = "checkbox", k(u, "section__toggle-wrapper"), k(d, "section__toggle"), k(g, "toggle__icon-on"), k(_, "toggle__icon-off"), k(f, "toggle__icon"), k(p, "toggle__icon-circle"), k(h, "toggle__label"), E(f, s, "true"), r ? (k(u, "toggle-service"), E(d, a, i), d.name = "cc-" + i + "-" + t, c.se[i][t] = d) : (d.name = "cc-" + t, c.ae[t] = d), r ? ((e) => {
		M(d, "change", () => {
			let t = c.se[e], n = c.ae[e];
			o.Z[e] = [];
			for (let n in t) {
				let r = t[n];
				r.checked && o.Z[e].push(r.value);
			}
			n.checked = o.Z[e].length > 0;
		});
	})(i) : ((e) => {
		M(d, l, () => {
			let t = c.se[e], n = d.checked;
			o.Z[e] = [];
			for (let r in t) t[r].checked = n, n && o.Z[e].push(r);
		});
	})(t), d.value = t, h.textContent = Ue(e), O(p, _), O(p, g), O(f, p), d.checked = r ? pe(i, t, !o.k) : pe(t, "", !o.k), n.readOnly && (d.disabled = !0), O(u, d), O(u, f), O(u, h), u;
}
var Ge = () => {
	let e = T("span");
	return m.ne.Xe || (m.ne.Xe = e), e;
}, Ke = (e, t) => {
	let n = m.o, r = m.ne, { hide: i, showPreferences: a, acceptCategory: f } = e, p = n.u && n.u.consentModal;
	if (!p) return;
	let h = p.acceptAllBtn, g = p.acceptNecessaryBtn, _ = p.showPreferencesBtn, v = p.closeIconLabel, y = p.footer, b = p.label, x = p.title, S = (e) => {
		i(), f(e);
	};
	if (!r.Ye) {
		r.Ye = T("div"), r.Me = T("div"), r.Ze = T("div"), r.et = T("div"), r.tt = T("div"), k(r.Ye, "cm-wrapper"), k(r.Me, "cm"), A(r.Ze, "body"), A(r.et, "texts"), A(r.tt, "btns"), E(r.Me, s, "true"), n.v ? (E(r.Me, "role", "dialog"), E(r.Me, "aria-modal", "true")) : E(r.Me, "role", "region"), !b && x && (E(r.Me, "aria-labelledby", "cm__title"), E(r.Me, "aria-describedby", "cm__desc"));
		let e = n.i.guiOptions, t = e && e.consentModal, i = (t && t.layout || "box").split(" ")[0] === "box";
		x && v && i && (r.Le || (r.Le = T(o), r.Le.innerHTML = we(), A(r.Le, "btn"), A(r.Le, "btn--close"), M(r.Le, l, () => {
			S([]);
		}), O(r.Ze, r.Le))), O(r.Ze, r.et), O(r.Me, r.Ze), O(r.Ye, r.Me), r.De = r.Me, E(r.De, "tabIndex", -1);
	}
	b && E(r.Me, "aria-label", b), r.Le && v && E(r.Le, "aria-label", v), x && (r.ot || (r.ot = T("h2"), r.ot.className = r.ot.id = "cm__title", O(r.et, r.ot)), r.ot.innerHTML = x);
	let C = p.description;
	if (C && (n.j && (C = C.replace("{{revisionMessage}}", n.I ? "" : p.revisionMessage || "")), r.nt || (r.nt = T("p"), r.nt.className = r.nt.id = "cm__desc", O(r.et, r.nt)), r.nt.innerHTML = C), h && (r.st || (r.st = T(o), O(r.st, Ge()), A(r.st, "btn"), E(r.st, u, "all"), M(r.st, l, () => {
		S("all");
	})), r.st.firstElementChild.innerHTML = h), g && (r.Pe || (r.Pe = T(o), O(r.Pe, Ge()), A(r.Pe, "btn"), E(r.Pe, u, "necessary"), M(r.Pe, l, () => {
		S([]);
	})), r.Pe.firstElementChild.innerHTML = g), _ && (r.ct || (r.ct = T(o), O(r.ct, Ge()), A(r.ct, "btn"), A(r.ct, "btn--secondary"), E(r.ct, u, "show"), E(r.ct, "aria-haspopup", "dialog"), M(r.ct, "mouseenter", () => {
		n.A || Re(e, t);
	}), M(r.ct, l, a)), r.ct.firstElementChild.innerHTML = _), r.rt || (r.rt = T("div", "cm__" + c), O(r.tt, r.rt)), r.st && O(r.rt, r.st), r.Pe && O(r.rt, r.Pe), r.ct && (r.Pe && r.st ? (r.it || (r.it = T("div", "cm__" + c), O(r.tt, r.it)), te(r.rt, "cm__" + c + "--uneven"), O(r.it, r.ct)) : (O(r.rt, r.ct), A(r.rt, c + "--uneven"))), (r.st || r.Pe || r.ct) && O(r.Ze, r.tt), y) {
		if (!r.lt) {
			let e = T("div"), t = T("div");
			r.lt = T("div"), A(e, "footer"), A(t, "links"), A(r.lt, "link-group"), O(t, r.lt), O(e, t), O(r.Me, e);
		}
		r.lt.innerHTML = y;
	}
	Ie(0), n.D || (n.D = !0, _e(m.re.ue, d, r.Me), t(), O(r.ke, r.Ye), be(r.Me)), Se(1), ye(r.Ze, e, Re, t);
}, qe = (e) => {
	if (!ce(e)) return null;
	let t = m.o._, n = (e) => Object.prototype.hasOwnProperty.call(t, e);
	if (n(e)) return e;
	let r = e.slice(0, 2);
	return n(r) ? r : null;
}, Je = () => m.o.l || m.o.i.language.default, Ye = (e) => {
	e && (m.o.l = e);
}, Xe = async (e) => {
	let t = m.o, n = qe(e) ?? Je(), r = t._[n];
	if (ce(r) ? r = await Qe(r) : ue(r) && (r = await r()), !r) throw `Could not load translation for the '${n}' language`;
	return t.u = r, Ye(n), !0;
}, Ze = () => {
	let e = m.o.i.language.rtl, t = m.ne.ke;
	e && t && (se(e) || (e = [e]), g(e, m.o.l) ? k(t, "cc--rtl") : te(t, "cc--rtl"));
}, Qe = async (e) => {
	try {
		let t = await fetch(e);
		return t.ok && await t.json();
	} catch (e) {
		return console.error(e), !1;
	}
}, $e = () => {
	let e = m.ne;
	e.ke || (e.ke = T("div"), e.ke.id = "cc-main", e.ke.setAttribute("data-nosnippet", ""), Ze(), (e.ve || e.We.body || e.be).appendChild(e.ke));
}, et = (e, t) => {
	try {
		return e();
	} catch (e) {
		return !t && console.warn("CookieConsent:", e), !1;
	}
}, tt = (e) => et(() => localStorage.removeItem(e)), nt = (e, t) => {
	if (t instanceof RegExp) return e.filter((e) => t.test(e));
	{
		let n = h(e, t);
		return n > -1 ? [e[n]] : [];
	}
}, rt = (t, n) => {
	let r = m.o;
	(t || n) && ve(), t && (_e(m.re.ie), _e(m.re.le), m.t.mode === e) ? r.L = !1 : (n && _e(m.re.de), r.L && (r.L = !1, location.reload()));
}, it = (e) => {
	let { hostname: t, protocol: n } = location, { name: r, path: i, domain: a, sameSite: o, useLocalStorage: s, secure: c } = m.t.cookie, l = e ? ut() : 864e5 * dt(), u = /* @__PURE__ */ new Date();
	u.setTime(u.getTime() + l), m.o.p.expirationTime = u.getTime();
	let d = JSON.stringify(m.o.p), f = r + "=" + encodeURIComponent(d) + (l === 0 ? "" : "; expires=" + u.toUTCString()) + "; Path=" + i + "; SameSite=" + o;
	a && g(t, ".") && (f += "; Domain=" + a), c && n === "https:" && (f += "; Secure"), s ? ((e, t) => {
		et(() => localStorage.setItem(e, t));
	})(r, d) : document.cookie = f, m.o.p;
}, at = (e, t, n) => {
	if (e.length === 0) return;
	let r = n || m.t.cookie.domain || location.hostname, i = t || m.t.cookie.path, a = r.slice(0, 4) === "www.", o = a && r.substring(4), s = (e, t) => {
		t && t.slice(0, 1) !== "." && (t = "." + t), document.cookie = e + "=; path=" + i + (t ? "; domain=" + t : "") + "; expires=Thu, 01 Jan 1970 00:00:01 GMT;";
	};
	for (let t of e) s(t, n), n || s(t, r), a && s(t, o);
}, ot = (e) => {
	let t = e || m.t.cookie.name, n = m.t.cookie.useLocalStorage;
	return ((e, t) => {
		let n;
		return n = et(() => JSON.parse(t ? e : decodeURIComponent(e)), !0), le(n) ? n : {};
	})(n ? (r = t, et(() => localStorage.getItem(r)) || "") : st(t, !0), n);
	var r;
}, st = (e, t) => {
	let n = e.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"), r = document.cookie.match("(^|;)\\s*" + n + "\\s*=\\s*([^;]+)");
	return r ? t ? r.pop() : e : "";
}, ct = (e) => {
	let t = document.cookie.split(/;\s*/), n = [];
	for (let r of t) {
		let t = r.split("=")[0];
		e ? et(() => {
			e.test(t) && n.push(t);
		}) : n.push(t);
	}
	return n;
}, lt = () => "10000000-1000-4000-8000-100000000000".replace(/[018]/g, (e) => (e ^ crypto.getRandomValues(/* @__PURE__ */ new Uint8Array(1))[0] & 15 >> e / 4).toString(16)), ut = () => {
	let e = m.o.S, t = e ? /* @__PURE__ */ new Date() - e : 0;
	return 864e5 * dt() - t;
}, dt = () => {
	let e = m.t.cookie.expiresAfterDays;
	return ue(e) ? e(m.o.V) : e;
}, ft = (e, n, r) => {
	((e, t) => {
		let { O: n, R: r, B: i, A: a, Z: o, $: s, X: c, k: l } = m.o, u = [];
		if (e) se(e) ? u.push(...e) : ce(e) && (u = e === "all" ? n : [e]);
		else if (u = l ? [...r, ...s] : [...r], a) {
			let e = m.ne.ae;
			u = [...de(), ...u.filter((t) => !e[t])];
		}
		if (u = u.filter((e) => g(n, e) && !g(t, e)), e) for (let e of n) o[e] = g(u, e) ? _(c[e]) : [];
		u.push(...i), he(u);
	})(e, n), (() => {
		let e = m.o, { Z: t, B: n, Y: r, X: i, O: a } = e, o = a;
		e.te = y(r);
		for (let e of o) {
			let a = i[e], o = _(a);
			o.length !== 0 && (r[e] = v(g(n, e) ? o : t[e] || []));
		}
	})(), ((e) => {
		let n = m.o;
		n.P = m.t.mode === t && n.k ? b(n.$, n.R) : b(n.R, n.p.categories);
		let r = n.P.length > 0, i = !1;
		for (let e of n.O) n.ee[e] = b(n.Y[e], n.te[e]), n.ee[e].length > 0 && (i = !0);
		me(!0), n.C ||= /* @__PURE__ */ new Date(), n.M ||= lt(), n.p = {
			categories: y(n.R),
			revision: m.t.revision,
			data: n.h,
			consentTimestamp: n.C.toISOString(),
			consentId: n.M,
			services: y(n.Y),
			languageCode: m.o.l
		}, n.S && (n.p.lastConsentTimestamp = n.S.toISOString());
		let a = !1, o = r || i;
		var s;
		(n.k || o) && (n.k && (n.k = !1, a = !0), n.S = n.S ? /* @__PURE__ */ new Date() : n.C, n.p.lastConsentTimestamp = n.S.toISOString(), it(), m.t.autoClearCookies && (a || o) && ((e) => {
			let t = m.o, n = ct(), r = ((e) => {
				let t = m.o;
				return (e ? t.O : t.P).filter((e) => {
					let n = t.F[e];
					return !!n && !n.readOnly && !!n.autoClear;
				});
			})(e);
			for (let e in t.ee) for (let r of t.ee[e]) {
				let i = t.X[e][r], a = i && i.cookies;
				if (!g(t.Y[e], r) && a) for (let e of a) at(nt(n, e.name), e.path, e.domain);
			}
			for (let i of r) {
				let r = t.F[i].autoClear, a = r && r.cookies || [], o = g(t.P, i), s = !g(t.R, i), c = o && s;
				if (e ? s : c) {
					r.reloadPage && c && (t.L = !0);
					for (let e of a) at(nt(n, e.name), e.path, e.domain);
				}
			}
		})(a)), e ? (s = () => n === m.o && rt(a, o), requestAnimationFrame(() => setTimeout(s))) : rt(a, o);
	})(r);
}, pt = (e, t = []) => ft(e, t), mt = () => !m.o.k || m.t.mode === t, ht = (e) => g(mt() ? m.o.R : [], e), gt = (e, t) => {
	let { O: n, X: r } = m.o;
	if (!(e && t && ce(t) && g(n, t) && _(r[t]).length !== 0)) return !1;
	((e, t) => {
		let n = m.o, { X: r, Z: i, A: a } = n, o = m.ne.se[t] || {}, s = m.ne.ae[t], c = _(r[t]);
		if (i[t] = [], ce(e)) {
			if (e === "all") {
				if (i[t].push(...c), a) for (let e in o) o[e].checked = !0, w(o[e]);
			} else if (g(c, e) && i[t].push(e), a) for (let t in o) o[t].checked = e === t, w(o[t]);
		} else if (se(e)) for (let n of c) {
			let r = g(e, n), a = o[n];
			r && i[t].push(n), a && (a.checked = r, w(a));
		}
		let l = i[t].length === 0;
		n.R = l ? n.R.filter((e) => e !== t) : v([...n.R, t]), s && (s.checked = !l, w(s));
	})(e, t), pt();
}, _t = (e, t) => g(mt() && m.o.Y[t] || [], e), vt = (e) => st(e, !0) !== "", yt = (e, t, n) => {
	let r = [], i = (e) => {
		if (ce(e)) {
			let t = st(e);
			t !== "" && r.push(t);
		} else r.push(...ct(e));
	};
	if (se(e)) for (let t of e) i(t);
	else i(e);
	at(r, t, n);
}, bt = (e) => {
	let { ne: t, o: r } = m;
	r.T || (r.D || !e && !r.k || Ke(Tt, $e), r.D && (r.T = !0, r.U = x(), r.v && oe(!0), ie(n, !0), D(t.Me, s), setTimeout(() => {
		k(t.Ye, "cc--anim");
	}, 100), setTimeout(() => {
		ne(t.De, !1, !0);
	}, 200), _e(m.re._e, d)));
}, xt = () => {
	let { ne: e, o: t, re: r } = m;
	t.T && (t.T = !1, t.v && oe(), ne(e.Xe, !0, !0), ie(n), E(e.Me, s, "true"), ne(t.U), t.U = null, setTimeout(() => te(e.Ye, "cc--anim"), 100), _e(r.fe, d));
}, St = () => {
	let { ne: e, o: t } = m;
	t.N || (t.A || Re(Tt, $e), t.A && (t.N = !0, t.T ? t.J = x() : t.U = x(), ie(r, !0), D(e.Ee, s), setTimeout(() => {
		ne(e.Te, !1, !0);
	}, 200), _e(m.re._e, f)));
}, Ct = () => me(Mt()), wt = () => {
	let e = m.o;
	e.N && (e.N = !1, Ct(), ne(m.ne.Ge, !0, !0), ie(r), E(m.ne.Ee, s, "true"), e.T ? (ne(e.J, !1, !0), e.J = null) : (ne(e.U), e.U = null), _e(m.re.fe, f));
}, Tt = {
	show: bt,
	hide: xt,
	showPreferences: St,
	hidePreferences: wt,
	acceptCategory: (e) => ft(e, [], !0)
}, Et = async (e, t) => {
	if (!qe(e)) return !1;
	let n = m.o;
	return !(e === Je() && !0 !== t || !await Xe(e) || (n.D && Ke(Tt, $e), n.A && (Ct(), Re(Tt, $e)), Ze(), 0));
}, Dt = () => {
	let { accepted: e, rejected: t } = (() => {
		let { k: e, R: t, O: n } = m.o;
		return {
			accepted: t,
			rejected: e ? [] : n.filter((e) => !g(t, e))
		};
	})(), n = (() => {
		let e = {}, t = {}, { k: n, O: r, X: i, R: a, Y: o } = m.o;
		for (let s of r) e[s] = g(a, s) ? o[s] : [], t[s] = n ? [] : b(e[s], _(i[s]));
		return {
			accepted: e,
			rejected: t
		};
	})();
	return y({
		acceptType: m.o.V,
		acceptedCategories: e,
		rejectedCategories: t,
		acceptedServices: n.accepted,
		rejectedServices: n.rejected
	});
}, Ot = (e, t) => {
	let n = [...document.scripts].find((t) => ee(t, "src") === e);
	return new Promise((r) => {
		if (n) return r(!0);
		if (n = T("script"), le(t)) for (let e in t) E(n, e, t[e]);
		n.onload = () => r(!0), n.onerror = () => {
			n.remove(), r(!1);
		}, n.src = e, O(document.head, n);
	});
}, kt = (e) => {
	let t, n = e.value, r = e.mode, i = !1, a = m.o;
	if (r === "update") {
		a.h = t = At("data");
		let e = typeof t == typeof n;
		if (e && typeof t == "object") {
			!t && (t = {});
			for (let e in n) t[e] !== n[e] && (t[e] = n[e], i = !0);
		} else !e && t || t === n || (t = n, i = !0);
	} else t = n, i = !0;
	return i && (a.h = t, a.p.data = t, it(!0)), i;
}, At = (e, t) => {
	let n = ot(t);
	return e ? n[e] : n;
}, jt = (e) => {
	let t = m.t, n = m.o.i;
	return e ? t[e] || n[e] : {
		...t,
		...n,
		cookie: { ...t.cookie }
	};
}, Mt = () => !m.o.k, Nt = (e) => {
	let t = e && new Date(e);
	return t && !isNaN(t) ? t : void 0;
}, Pt = async (e) => {
	let { o: n, t: r, re: i } = m, o = window;
	if (!o._ccRun) {
		if (o._ccRun = !0, ((e) => {
			let { ne: n, t: r, o: i } = m, o = r, s = i, { cookie: c } = o, l = m.ce, u = e.cookie, d = e.categories || {}, f = _(d), p = navigator, h = document;
			n.We = h, n.be = h.documentElement;
			let v = e.root;
			n.ve = v && ce(v) ? h.querySelector(v) : v, c.domain = location.hostname, s.i = e, s.F = d, s.O = f, s._ = e.language.translations, s.v = !!e.disablePageInteraction, l.ie = e.onFirstConsent, l.le = e.onConsent, l.de = e.onChange, l.fe = e.onModalHide, l._e = e.onModalShow, l.ue = e.onModalReady;
			let { mode: y, autoShow: b, lazyHtmlGeneration: x, autoClearCookies: S, revision: w, manageScriptTags: T, hideFromBots: E, respectGpc: D } = e;
			y === t && (o.mode = y), typeof S == "boolean" && (o.autoClearCookies = S), typeof T == "boolean" && (o.manageScriptTags = T), typeof w == "number" && w >= 0 && (o.revision = w, s.j = !0), typeof b == "boolean" && (o.autoShow = b), typeof x == "boolean" && (o.lazyHtmlGeneration = x), !1 === E && (o.hideFromBots = !1), !0 === o.hideFromBots && p && (s.G = p.userAgent && /bot|crawl|spider|slurp|teoma/i.test(p.userAgent) || p.webdriver), typeof D == "boolean" && (o.respectGpc = D), le(u) && (o.cookie = {
				...c,
				...u
			}), o.autoClearCookies, s.j, o.manageScriptTags, ((e) => {
				let { F: t, X: n, Y: r, Z: i, B: a } = m.o;
				for (let o of e) {
					let e = t[o], s = e.services || {}, c = le(s) && _(s) || [];
					n[o] = {}, r[o] = [], i[o] = [], e.readOnly && (a.push(o), r[o] = c), m.ne.se[o] = {};
					for (let e of c) {
						let t = s[e];
						t.he = !1, n[o][e] = t;
					}
				}
			})(f), (() => {
				if (!m.t.manageScriptTags) return;
				let e = m.o, t = C(document, "script[" + a + "]");
				for (let n of t) {
					let t = ee(n, a), r = n.dataset.service || "", i = !1;
					if (t && t.charAt(0) === "!" && (t = t.slice(1), i = !0), r.charAt(0) === "!" && (r = r.slice(1), i = !0), g(e.O, t) && (e.oe.push({
						Ce: n,
						ye: !1,
						xe: i,
						we: t,
						Se: r
					}), r)) {
						let n = e.X[t];
						n[r] || (n[r] = { he: !1 });
					}
				}
			})(), Ye((() => {
				let e = m.o.i.language.autoDetect;
				if (e) {
					let t = {
						browser: navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language],
						document: [document.documentElement.lang]
					}[e];
					if (se(t)) for (let e of t) {
						let t = qe(e);
						if (t) return t;
					}
				}
				return Je();
			})());
		})(e), n.G) return;
		(() => {
			let e = m.o, n = m.t, r = ot();
			n.cookie.useLocalStorage && (/* @__PURE__ */ new Date()).getTime() > (r.expirationTime || 0) && (tt(n.cookie.name), r = {});
			let { categories: i, services: a, consentId: o, consentTimestamp: s, lastConsentTimestamp: c, data: l, revision: u } = r, d = se(i);
			e.p = r, e.M = o;
			let f = !!o && ce(o);
			if (e.C = Nt(s), e.S = Nt(c), e.h = l === void 0 ? null : l, e.j && f && u !== n.revision && (e.I = !1), e.k = !(f && e.I && e.C && e.S && d), e.k, (() => {
				let e = m.o;
				for (let n of e.O) {
					let r = e.F[n];
					if (r.readOnly || r.enabled && !fe()) {
						e.$.push(n);
						let r = e.X[n] || {};
						for (let i in r) e.Z[n].push(i), e.i.mode !== t || g(e.Y[n], i) || e.Y[n].push(i);
					}
				}
			})(), e.k) n.mode === t && (e.R = [...e.$]);
			else {
				for (let t of e.O) {
					let n = a && a[t], r = _(e.X[t]);
					g(e.B, t) ? e.Y[t] = r : se(n) ? e.Y[t] = n.filter((e) => g(r, e)) : g(i, t) || (e.Y[t] = []);
				}
				e.Z = { ...e.Y }, he([...e.B, ...i.filter((t) => g(e.O, t))]);
			}
		})();
		let c = Mt(), l;
		if (await Xe().catch((e) => {
			l = e;
		}) && (ye(null, s = Tt, Re, $e), m.o.k && !m.t.lazyHtmlGeneration && Ke(s, $e), m.t.lazyHtmlGeneration || Re(s, $e), !r.autoShow || Mt() || fe() || bt(!0)), c ? (ve(), _e(i.le)) : r.mode === t && ve(), l) throw l;
	}
	var s;
};
Object.freeze({
	__proto__: null,
	acceptCategory: pt,
	acceptService: gt,
	acceptedCategory: ht,
	acceptedService: _t,
	eraseCookies: yt,
	getConfig: jt,
	getCookie: At,
	getUserPreferences: Dt,
	hide: xt,
	hidePreferences: wt,
	loadScript: Ot,
	reset: (e) => {
		let { ke: t } = m.ne, { name: a, path: o, domain: s, useLocalStorage: c } = m.t.cookie;
		e && (c ? tt(a) : yt(a, o, s));
		for (let { pe: e, me: t, ge: n } of m.o.m) e.removeEventListener(t, n);
		t && t.remove();
		for (let e of re()) e.classList.remove(i, r, n);
		let l = new p();
		for (let e in m) m[e] = l[e];
		window._ccRun = !1;
	},
	run: Pt,
	setCookieData: kt,
	setLanguage: Et,
	show: bt,
	showPreferences: St,
	validConsent: Mt,
	validCookie: vt
});
// resources/js/usage-payload.js
var Ft = ["signup_submitted", "signup_completed"], It = [
	"cloud",
	"personal",
	"desktop",
	"demo"
], Lt = [
	"home",
	"overview",
	"project",
	"new-project",
	"project-settings",
	"settings",
	"profile",
	"events",
	"add-source",
	"workspaces",
	"new-workspace",
	"login",
	"signup",
	"check-email"
];
function Rt(e) {
	return e === "cloud" ? "cc_cookie" : `metribay_${["desktop", "demo"].includes(e) ? e : "personal"}_usage`;
}
function zt(e, t) {
	try {
		let n = e.split(";").map((e) => e.trim()).find((e) => e.startsWith(`${t}=`)), r = JSON.parse(decodeURIComponent(n?.slice(t.length + 1) || ""));
		return r.revision === 1 && Array.isArray(r.categories) && r.categories.includes("analytics");
	} catch {
		return !1;
	}
}
function Bt(e, t = null) {
	return !Lt.includes(e.page) || !It.includes(e.edition) || t !== null && !Ft.includes(t) ? null : {
		website: e.website,
		hostname: e.edition === "cloud" ? "app.metribay.com" : `metribay.${e.edition}`,
		url: `/${e.edition}/${e.page}`,
		title: `Metribay ${e.edition}: ${e.page}`,
		referrer: "",
		...t ? { name: t } : {}
	};
}
// resources/js/usage-tracker.js
function Vt(e, { allowed: t, appendScript: n, track: r }) {
	let i = !1, a = !1, o = () => {
		if (a || !t()) return;
		a = !0, r(Bt(e));
		let n = e.event && Bt(e, e.event);
		n && t() && r(n);
	};
	return {
		get started() {
			return i;
		},
		enable() {
			t() && !i && (i = !0, n(o, () => {
				i = !1;
			}));
		},
		beforeSend(n, r) {
			return !t() || n !== "event" ? !1 : Bt(e, r.name || null) || !1;
		}
	};
}
// resources/js/usage-analytics.js
var Ht = document.getElementById("metribay-analytics");
if (Ht) {
	let e;
	try {
		e = JSON.parse(Ht.textContent);
	} catch {}
	e && Bt(e) && Ut(e);
}
function Ut(e) {
	let t = (t) => e.strings?.[t] || t, n = e.edition === "cloud", r = Rt(e.edition), i = () => zt(document.cookie, r), a = Vt(e, {
		allowed: i,
		track: (e) => window.umami?.track(e),
		appendScript: (t, n) => {
			let r = document.createElement("script");
			r.defer = !0, r.src = e.script, r.referrerPolicy = "no-referrer", r.dataset.websiteId = e.website, r.dataset.autoTrack = "false", r.dataset.beforeSend = "metribayUsageBeforeSend", r.onload = t, r.onerror = () => {
				n(), r.remove();
			}, document.head.append(r);
		}
	});
	window.metribayUsageBeforeSend = a.beforeSend;
	let o = () => a.enable(), s = () => {
		a.started && !i() ? window.location.reload() : o();
	}, c = t(n ? "With your permission, we use Umami to measure visits and how Metribay is used. No advertising or session recordings. Your choice applies to this website and Metribay Cloud." : "Help improve Metribay by sharing limited usage statistics. This is optional and stays off unless you agree.");
	Pt({
		mode: "opt-in",
		revision: 1,
		manageScriptTags: !1,
		disablePageInteraction: !1,
		cookie: {
			name: r,
			domain: n && ["metribay.com", "app.metribay.com"].includes(location.hostname) ? "metribay.com" : "",
			path: "/",
			secure: location.protocol === "https:",
			sameSite: "Lax",
			expiresAfterDays: 182
		},
		guiOptions: {
			consentModal: {
				layout: "box",
				position: "bottom right",
				equalWeightButtons: !0
			},
			preferencesModal: {
				layout: "box",
				equalWeightButtons: !0
			}
		},
		categories: {
			necessary: {
				enabled: !0,
				readOnly: !0
			},
			analytics: { enabled: !1 }
		},
		language: {
			default: "current",
			translations: { current: {
				consentModal: {
					title: t(n ? "Your privacy choices" : "Share usage statistics?"),
					description: c,
					acceptAllBtn: t("Accept analytics"),
					acceptNecessaryBtn: t("Reject analytics"),
					showPreferencesBtn: t("Preferences"),
					footer: `<a href="https://metribay.com/privacy" target="_blank" rel="noopener noreferrer">${t("Privacy policy")}</a>`
				},
				preferencesModal: {
					title: t("Privacy settings"),
					acceptAllBtn: t("Accept analytics"),
					acceptNecessaryBtn: t("Reject analytics"),
					savePreferencesBtn: t("Save preferences"),
					closeIconLabel: t("Close"),
					sections: [{
						title: t("Necessary"),
						description: t("Required for sign-in, security and remembering this choice."),
						linkedCategory: "necessary"
					}, {
						title: t("Analytics"),
						description: t("Optional page views and usage events sent to s.worbee.com. No names, emails or project data.") + " " + t("The analytics server receives your IP address to process requests."),
						linkedCategory: "analytics"
					}]
				}
			} }
		},
		onConsent: o,
		onChange: s
	}), window.addEventListener("focus", s), document.addEventListener("visibilitychange", () => {
		document.hidden || s();
	});
}
// node_modules/@kurkle/color/dist/color.esm.js
function Wt(e) {
	return e + .5 | 0;
}
var Gt = (e, t, n) => Math.max(Math.min(e, n), t);
function Kt(e) {
	return Gt(Wt(e * 2.55), 0, 255);
}
function qt(e) {
	return Gt(Wt(e * 255), 0, 255);
}
function Jt(e) {
	return Gt(Wt(e / 2.55) / 100, 0, 1);
}
function Yt(e) {
	return Gt(Wt(e * 100), 0, 100);
}
var N = {
	0: 0,
	1: 1,
	2: 2,
	3: 3,
	4: 4,
	5: 5,
	6: 6,
	7: 7,
	8: 8,
	9: 9,
	A: 10,
	B: 11,
	C: 12,
	D: 13,
	E: 14,
	F: 15,
	a: 10,
	b: 11,
	c: 12,
	d: 13,
	e: 14,
	f: 15
}, Xt = [..."0123456789ABCDEF"], Zt = (e) => Xt[e & 15], Qt = (e) => Xt[(e & 240) >> 4] + Xt[e & 15], $t = (e) => (e & 240) >> 4 == (e & 15), en = (e) => $t(e.r) && $t(e.g) && $t(e.b) && $t(e.a);
function tn(e) {
	var t = e.length, n;
	return e[0] === "#" && (t === 4 || t === 5 ? n = {
		r: 255 & N[e[1]] * 17,
		g: 255 & N[e[2]] * 17,
		b: 255 & N[e[3]] * 17,
		a: t === 5 ? N[e[4]] * 17 : 255
	} : (t === 7 || t === 9) && (n = {
		r: N[e[1]] << 4 | N[e[2]],
		g: N[e[3]] << 4 | N[e[4]],
		b: N[e[5]] << 4 | N[e[6]],
		a: t === 9 ? N[e[7]] << 4 | N[e[8]] : 255
	})), n;
}
var nn = (e, t) => e < 255 ? t(e) : "";
function rn(e) {
	var t = en(e) ? Zt : Qt;
	return e ? "#" + t(e.r) + t(e.g) + t(e.b) + nn(e.a, t) : void 0;
}
var an = /^(hsla?|hwb|hsv)\(\s*([-+.e\d]+)(?:deg)?[\s,]+([-+.e\d]+)%[\s,]+([-+.e\d]+)%(?:[\s,]+([-+.e\d]+)(%)?)?\s*\)$/;
function on(e, t, n) {
	let r = t * Math.min(n, 1 - n), i = (t, i = (t + e / 30) % 12) => n - r * Math.max(Math.min(i - 3, 9 - i, 1), -1);
	return [
		i(0),
		i(8),
		i(4)
	];
}
function sn(e, t, n) {
	let r = (r, i = (r + e / 60) % 6) => n - n * t * Math.max(Math.min(i, 4 - i, 1), 0);
	return [
		r(5),
		r(3),
		r(1)
	];
}
function cn(e, t, n) {
	let r = on(e, 1, .5), i;
	for (t + n > 1 && (i = 1 / (t + n), t *= i, n *= i), i = 0; i < 3; i++) r[i] *= 1 - t - n, r[i] += t;
	return r;
}
function ln(e, t, n, r, i) {
	return e === i ? (t - n) / r + (t < n ? 6 : 0) : t === i ? (n - e) / r + 2 : (e - t) / r + 4;
}
function un(e) {
	let t = e.r / 255, n = e.g / 255, r = e.b / 255, i = Math.max(t, n, r), a = Math.min(t, n, r), o = (i + a) / 2, s, c, l;
	return i !== a && (l = i - a, c = o > .5 ? l / (2 - i - a) : l / (i + a), s = ln(t, n, r, l, i), s = s * 60 + .5), [
		s | 0,
		c || 0,
		o
	];
}
function dn(e, t, n, r) {
	return (Array.isArray(t) ? e(t[0], t[1], t[2]) : e(t, n, r)).map(qt);
}
function fn(e, t, n) {
	return dn(on, e, t, n);
}
function pn(e, t, n) {
	return dn(cn, e, t, n);
}
function mn(e, t, n) {
	return dn(sn, e, t, n);
}
function hn(e) {
	return (e % 360 + 360) % 360;
}
function gn(e) {
	let t = an.exec(e), n = 255, r;
	if (!t) return;
	t[5] !== r && (n = t[6] ? Kt(+t[5]) : qt(+t[5]));
	let i = hn(+t[2]), a = t[3] / 100, o = t[4] / 100;
	return r = t[1] === "hwb" ? pn(i, a, o) : t[1] === "hsv" ? mn(i, a, o) : fn(i, a, o), {
		r: r[0],
		g: r[1],
		b: r[2],
		a: n
	};
}
function _n(e, t) {
	var n = un(e);
	n[0] = hn(n[0] + t), n = fn(n), e.r = n[0], e.g = n[1], e.b = n[2];
}
function vn(e) {
	if (!e) return;
	let t = un(e), n = t[0], r = Yt(t[1]), i = Yt(t[2]);
	return e.a < 255 ? `hsla(${n}, ${r}%, ${i}%, ${Jt(e.a)})` : `hsl(${n}, ${r}%, ${i}%)`;
}
var yn = {
	x: "dark",
	Z: "light",
	Y: "re",
	X: "blu",
	W: "gr",
	V: "medium",
	U: "slate",
	A: "ee",
	T: "ol",
	S: "or",
	B: "ra",
	C: "lateg",
	D: "ights",
	R: "in",
	Q: "turquois",
	E: "hi",
	P: "ro",
	O: "al",
	N: "le",
	M: "de",
	L: "yello",
	F: "en",
	K: "ch",
	G: "arks",
	H: "ea",
	I: "ightg",
	J: "wh"
}, bn = {
	OiceXe: "f0f8ff",
	antiquewEte: "faebd7",
	aqua: "ffff",
	aquamarRe: "7fffd4",
	azuY: "f0ffff",
	beige: "f5f5dc",
	bisque: "ffe4c4",
	black: "0",
	blanKedOmond: "ffebcd",
	Xe: "ff",
	XeviTet: "8a2be2",
	bPwn: "a52a2a",
	burlywood: "deb887",
	caMtXe: "5f9ea0",
	KartYuse: "7fff00",
	KocTate: "d2691e",
	cSO: "ff7f50",
	cSnflowerXe: "6495ed",
	cSnsilk: "fff8dc",
	crimson: "dc143c",
	cyan: "ffff",
	xXe: "8b",
	xcyan: "8b8b",
	xgTMnPd: "b8860b",
	xWay: "a9a9a9",
	xgYF: "6400",
	xgYy: "a9a9a9",
	xkhaki: "bdb76b",
	xmagFta: "8b008b",
	xTivegYF: "556b2f",
	xSange: "ff8c00",
	xScEd: "9932cc",
	xYd: "8b0000",
	xsOmon: "e9967a",
	xsHgYF: "8fbc8f",
	xUXe: "483d8b",
	xUWay: "2f4f4f",
	xUgYy: "2f4f4f",
	xQe: "ced1",
	xviTet: "9400d3",
	dAppRk: "ff1493",
	dApskyXe: "bfff",
	dimWay: "696969",
	dimgYy: "696969",
	dodgerXe: "1e90ff",
	fiYbrick: "b22222",
	flSOwEte: "fffaf0",
	foYstWAn: "228b22",
	fuKsia: "ff00ff",
	gaRsbSo: "dcdcdc",
	ghostwEte: "f8f8ff",
	gTd: "ffd700",
	gTMnPd: "daa520",
	Way: "808080",
	gYF: "8000",
	gYFLw: "adff2f",
	gYy: "808080",
	honeyMw: "f0fff0",
	hotpRk: "ff69b4",
	RdianYd: "cd5c5c",
	Rdigo: "4b0082",
	ivSy: "fffff0",
	khaki: "f0e68c",
	lavFMr: "e6e6fa",
	lavFMrXsh: "fff0f5",
	lawngYF: "7cfc00",
	NmoncEffon: "fffacd",
	ZXe: "add8e6",
	ZcSO: "f08080",
	Zcyan: "e0ffff",
	ZgTMnPdLw: "fafad2",
	ZWay: "d3d3d3",
	ZgYF: "90ee90",
	ZgYy: "d3d3d3",
	ZpRk: "ffb6c1",
	ZsOmon: "ffa07a",
	ZsHgYF: "20b2aa",
	ZskyXe: "87cefa",
	ZUWay: "778899",
	ZUgYy: "778899",
	ZstAlXe: "b0c4de",
	ZLw: "ffffe0",
	lime: "ff00",
	limegYF: "32cd32",
	lRF: "faf0e6",
	magFta: "ff00ff",
	maPon: "800000",
	VaquamarRe: "66cdaa",
	VXe: "cd",
	VScEd: "ba55d3",
	VpurpN: "9370db",
	VsHgYF: "3cb371",
	VUXe: "7b68ee",
	VsprRggYF: "fa9a",
	VQe: "48d1cc",
	VviTetYd: "c71585",
	midnightXe: "191970",
	mRtcYam: "f5fffa",
	mistyPse: "ffe4e1",
	moccasR: "ffe4b5",
	navajowEte: "ffdead",
	navy: "80",
	Tdlace: "fdf5e6",
	Tive: "808000",
	TivedBb: "6b8e23",
	Sange: "ffa500",
	SangeYd: "ff4500",
	ScEd: "da70d6",
	pOegTMnPd: "eee8aa",
	pOegYF: "98fb98",
	pOeQe: "afeeee",
	pOeviTetYd: "db7093",
	papayawEp: "ffefd5",
	pHKpuff: "ffdab9",
	peru: "cd853f",
	pRk: "ffc0cb",
	plum: "dda0dd",
	powMrXe: "b0e0e6",
	purpN: "800080",
	YbeccapurpN: "663399",
	Yd: "ff0000",
	Psybrown: "bc8f8f",
	PyOXe: "4169e1",
	saddNbPwn: "8b4513",
	sOmon: "fa8072",
	sandybPwn: "f4a460",
	sHgYF: "2e8b57",
	sHshell: "fff5ee",
	siFna: "a0522d",
	silver: "c0c0c0",
	skyXe: "87ceeb",
	UXe: "6a5acd",
	UWay: "708090",
	UgYy: "708090",
	snow: "fffafa",
	sprRggYF: "ff7f",
	stAlXe: "4682b4",
	tan: "d2b48c",
	teO: "8080",
	tEstN: "d8bfd8",
	tomato: "ff6347",
	Qe: "40e0d0",
	viTet: "ee82ee",
	JHt: "f5deb3",
	wEte: "ffffff",
	wEtesmoke: "f5f5f5",
	Lw: "ffff00",
	LwgYF: "9acd32"
};
function xn() {
	let e = {}, t = Object.keys(bn), n = Object.keys(yn), r, i, a, o, s;
	for (r = 0; r < t.length; r++) {
		for (o = s = t[r], i = 0; i < n.length; i++) a = n[i], s = s.replace(a, yn[a]);
		a = parseInt(bn[o], 16), e[s] = [
			a >> 16 & 255,
			a >> 8 & 255,
			a & 255
		];
	}
	return e;
}
var Sn;
function Cn(e) {
	Sn || (Sn = xn(), Sn.transparent = [
		0,
		0,
		0,
		0
	]);
	let t = Sn[e.toLowerCase()];
	return t && {
		r: t[0],
		g: t[1],
		b: t[2],
		a: t.length === 4 ? t[3] : 255
	};
}
var wn = /^rgba?\(\s*([-+.\d]+)(%)?[\s,]+([-+.e\d]+)(%)?[\s,]+([-+.e\d]+)(%)?(?:[\s,/]+([-+.e\d]+)(%)?)?\s*\)$/;
function Tn(e) {
	let t = wn.exec(e), n = 255, r, i, a;
	if (t) {
		if (t[7] !== r) {
			let e = +t[7];
			n = t[8] ? Kt(e) : Gt(e * 255, 0, 255);
		}
		return r = +t[1], i = +t[3], a = +t[5], r = 255 & (t[2] ? Kt(r) : Gt(r, 0, 255)), i = 255 & (t[4] ? Kt(i) : Gt(i, 0, 255)), a = 255 & (t[6] ? Kt(a) : Gt(a, 0, 255)), {
			r,
			g: i,
			b: a,
			a: n
		};
	}
}
function En(e) {
	return e && (e.a < 255 ? `rgba(${e.r}, ${e.g}, ${e.b}, ${Jt(e.a)})` : `rgb(${e.r}, ${e.g}, ${e.b})`);
}
var Dn = (e) => e <= .0031308 ? e * 12.92 : e ** (1 / 2.4) * 1.055 - .055, On = (e) => e <= .04045 ? e / 12.92 : ((e + .055) / 1.055) ** 2.4;
function kn(e, t, n) {
	let r = On(Jt(e.r)), i = On(Jt(e.g)), a = On(Jt(e.b));
	return {
		r: qt(Dn(r + n * (On(Jt(t.r)) - r))),
		g: qt(Dn(i + n * (On(Jt(t.g)) - i))),
		b: qt(Dn(a + n * (On(Jt(t.b)) - a))),
		a: e.a + n * (t.a - e.a)
	};
}
function An(e, t, n) {
	if (e) {
		let r = un(e);
		r[t] = Math.max(0, Math.min(r[t] + r[t] * n, t === 0 ? 360 : 1)), r = fn(r), e.r = r[0], e.g = r[1], e.b = r[2];
	}
}
function jn(e, t) {
	return e && Object.assign(t || {}, e);
}
function Mn(e) {
	var t = {
		r: 0,
		g: 0,
		b: 0,
		a: 255
	};
	return Array.isArray(e) ? e.length >= 3 && (t = {
		r: e[0],
		g: e[1],
		b: e[2],
		a: 255
	}, e.length > 3 && (t.a = qt(e[3]))) : (t = jn(e, {
		r: 0,
		g: 0,
		b: 0,
		a: 1
	}), t.a = qt(t.a)), t;
}
function Nn(e) {
	return e.charAt(0) === "r" ? Tn(e) : gn(e);
}
var Pn = class e {
	constructor(t) {
		if (t instanceof e) return t;
		let n = typeof t, r;
		n === "object" ? r = Mn(t) : n === "string" && (r = tn(t) || Cn(t) || Nn(t)), this._rgb = r, this._valid = !!r;
	}
	get valid() {
		return this._valid;
	}
	get rgb() {
		var e = jn(this._rgb);
		return e && (e.a = Jt(e.a)), e;
	}
	set rgb(e) {
		this._rgb = Mn(e);
	}
	rgbString() {
		return this._valid ? En(this._rgb) : void 0;
	}
	hexString() {
		return this._valid ? rn(this._rgb) : void 0;
	}
	hslString() {
		return this._valid ? vn(this._rgb) : void 0;
	}
	mix(e, t) {
		if (e) {
			let n = this.rgb, r = e.rgb, i, a = t === i ? .5 : t, o = 2 * a - 1, s = n.a - r.a, c = ((o * s === -1 ? o : (o + s) / (1 + o * s)) + 1) / 2;
			i = 1 - c, n.r = 255 & c * n.r + i * r.r + .5, n.g = 255 & c * n.g + i * r.g + .5, n.b = 255 & c * n.b + i * r.b + .5, n.a = a * n.a + (1 - a) * r.a, this.rgb = n;
		}
		return this;
	}
	interpolate(e, t) {
		return e && (this._rgb = kn(this._rgb, e._rgb, t)), this;
	}
	clone() {
		return new e(this.rgb);
	}
	alpha(e) {
		return this._rgb.a = qt(e), this;
	}
	clearer(e) {
		let t = this._rgb;
		return t.a *= 1 - e, this;
	}
	greyscale() {
		let e = this._rgb;
		return e.r = e.g = e.b = Wt(e.r * .3 + e.g * .59 + e.b * .11), this;
	}
	opaquer(e) {
		let t = this._rgb;
		return t.a *= 1 + e, this;
	}
	negate() {
		let e = this._rgb;
		return e.r = 255 - e.r, e.g = 255 - e.g, e.b = 255 - e.b, this;
	}
	lighten(e) {
		return An(this._rgb, 2, e), this;
	}
	darken(e) {
		return An(this._rgb, 2, -e), this;
	}
	saturate(e) {
		return An(this._rgb, 1, e), this;
	}
	desaturate(e) {
		return An(this._rgb, 1, -e), this;
	}
	rotate(e) {
		return _n(this._rgb, e), this;
	}
};
// node_modules/chart.js/dist/chunks/index.js
function Fn() {}
var In = (() => {
	let e = 0;
	return () => e++;
})();
function P(e) {
	return e == null;
}
function F(e) {
	if (Array.isArray && Array.isArray(e)) return !0;
	let t = Object.prototype.toString.call(e);
	return t.slice(0, 7) === "[object" && t.slice(-6) === "Array]";
}
function I(e) {
	return e !== null && Object.prototype.toString.call(e) === "[object Object]";
}
function L(e) {
	return (typeof e == "number" || e instanceof Number) && isFinite(+e);
}
function R(e, t) {
	return L(e) ? e : t;
}
function z(e, t) {
	return e === void 0 ? t : e;
}
var Ln = (e, t) => typeof e == "string" && e.endsWith("%") ? parseFloat(e) / 100 * t : +e;
function B(e, t, n) {
	if (e && typeof e.call == "function") return e.apply(n, t);
}
function V(e, t, n, r) {
	let i, a, o;
	if (F(e)) {
		if (a = e.length, r) for (i = a - 1; i >= 0; i--) t.call(n, e[i], i);
		else for (i = 0; i < a; i++) t.call(n, e[i], i);
	} else if (I(e)) for (o = Object.keys(e), a = o.length, i = 0; i < a; i++) t.call(n, e[o[i]], o[i]);
}
function Rn(e, t) {
	let n, r, i, a;
	if (!e || !t || e.length !== t.length) return !1;
	for (n = 0, r = e.length; n < r; ++n) if (i = e[n], a = t[n], i.datasetIndex !== a.datasetIndex || i.index !== a.index) return !1;
	return !0;
}
function zn(e) {
	if (F(e)) return e.map(zn);
	if (I(e)) {
		let t = Object.create(null), n = Object.keys(e), r = n.length, i = 0;
		for (; i < r; ++i) t[n[i]] = zn(e[n[i]]);
		return t;
	}
	return e;
}
function Bn(e) {
	return [
		"__proto__",
		"prototype",
		"constructor"
	].indexOf(e) === -1;
}
function Vn(e, t, n, r) {
	if (!Bn(e)) return;
	let i = t[e], a = n[e];
	I(i) && I(a) ? Hn(i, a, r) : t[e] = zn(a);
}
function Hn(e, t, n) {
	let r = F(t) ? t : [t], i = r.length;
	if (!I(e)) return e;
	n ||= {};
	let a = n.merger || Vn, o;
	for (let t = 0; t < i; ++t) {
		if (o = r[t], !I(o)) continue;
		let i = Object.keys(o);
		for (let t = 0, r = i.length; t < r; ++t) a(i[t], e, o, n);
	}
	return e;
}
function Un(e, t) {
	return Hn(e, t, { merger: Wn });
}
function Wn(e, t, n) {
	if (!Bn(e)) return;
	let r = t[e], i = n[e];
	I(r) && I(i) ? Un(r, i) : Object.prototype.hasOwnProperty.call(t, e) || (t[e] = zn(i));
}
var Gn = {
	"": (e) => e,
	x: (e) => e.x,
	y: (e) => e.y
};
function Kn(e) {
	let t = e.split("."), n = [], r = "";
	for (let e of t) r += e, r.endsWith("\\") ? r = r.slice(0, -1) + "." : (n.push(r), r = "");
	return n;
}
function qn(e) {
	let t = Kn(e);
	return (e) => {
		for (let n of t) {
			if (n === "") break;
			e &&= e[n];
		}
		return e;
	};
}
function Jn(e, t) {
	return (Gn[t] || (Gn[t] = qn(t)))(e);
}
function Yn(e) {
	return e.charAt(0).toUpperCase() + e.slice(1);
}
var Xn = (e) => e !== void 0, Zn = (e) => typeof e == "function", Qn = (e, t) => {
	if (e.size !== t.size) return !1;
	for (let n of e) if (!t.has(n)) return !1;
	return !0;
};
function $n(e) {
	return e.type === "mouseup" || e.type === "click" || e.type === "contextmenu";
}
var H = Math.PI, U = 2 * H, er = U + H, tr = Infinity, nr = H / 180, W = H / 2, rr = H / 4, ir = H * 2 / 3, ar = Math.log10, or = Math.sign;
function sr(e, t, n) {
	return Math.abs(e - t) < n;
}
function cr(e) {
	let t = Math.round(e);
	e = sr(e, t, e / 1e3) ? t : e;
	let n = 10 ** Math.floor(ar(e)), r = e / n;
	return (r <= 1 ? 1 : r <= 2 ? 2 : r <= 5 ? 5 : 10) * n;
}
function lr(e) {
	let t = [], n = Math.sqrt(e), r = 1;
	for (; r < n; r++) e % r === 0 && (t.push(r), t.push(e / r));
	return n === (n | 0) && t.push(n), t.sort((e, t) => e - t).pop(), t;
}
function ur(e) {
	return typeof e == "symbol" || typeof e == "object" && !!e && !(Symbol.toPrimitive in e || "toString" in e || "valueOf" in e);
}
function dr(e) {
	return !ur(e) && !isNaN(parseFloat(e)) && isFinite(e);
}
function fr(e, t) {
	let n = Math.round(e);
	return n - t <= e && n + t >= e;
}
function pr(e, t, n) {
	let r, i, a;
	for (r = 0, i = e.length; r < i; r++) a = e[r][n], isNaN(a) || (t.min = Math.min(t.min, a), t.max = Math.max(t.max, a));
}
function mr(e) {
	return H / 180 * e;
}
function hr(e) {
	return 180 / H * e;
}
function gr(e) {
	if (!L(e)) return;
	let t = 1, n = 0;
	for (; Math.round(e * t) / t !== e;) t *= 10, n++;
	return n;
}
function _r(e, t) {
	let n = t.x - e.x, r = t.y - e.y, i = Math.sqrt(n * n + r * r), a = Math.atan2(r, n);
	return a < -.5 * H && (a += U), {
		angle: a,
		distance: i
	};
}
function vr(e, t) {
	return Math.sqrt((t.x - e.x) ** 2 + (t.y - e.y) ** 2);
}
function yr(e, t) {
	return (e - t + er) % U - H;
}
function G(e) {
	return (e % U + U) % U;
}
function br(e, t, n, r) {
	let i = G(e), a = G(t), o = G(n), s = G(a - i), c = G(o - i), l = G(i - a), u = G(i - o);
	return i === a || i === o || r && a === o || s > c && l < u;
}
function K(e, t, n) {
	return Math.max(t, Math.min(n, e));
}
function xr(e) {
	return K(e, -32768, 32767);
}
function Sr(e, t, n, r = 1e-6) {
	return e >= Math.min(t, n) - r && e <= Math.max(t, n) + r;
}
function Cr(e, t, n) {
	n ||= ((n) => e[n] < t);
	let r = e.length - 1, i = 0, a;
	for (; r - i > 1;) a = i + r >> 1, n(a) ? i = a : r = a;
	return {
		lo: i,
		hi: r
	};
}
var wr = (e, t, n, r) => Cr(e, n, r ? (r) => {
	let i = e[r][t];
	return i < n || i === n && e[r + 1][t] === n;
} : (r) => e[r][t] < n), Tr = (e, t, n) => Cr(e, n, (r) => e[r][t] >= n);
function Er(e, t, n) {
	let r = 0, i = e.length;
	for (; r < i && e[r] < t;) r++;
	for (; i > r && e[i - 1] > n;) i--;
	return r > 0 || i < e.length ? e.slice(r, i) : e;
}
var Dr = [
	"push",
	"pop",
	"shift",
	"splice",
	"unshift"
];
function Or(e, t) {
	e._chartjs ? e._chartjs.listeners.push(t) : (Object.defineProperty(e, "_chartjs", {
		configurable: !0,
		enumerable: !1,
		value: { listeners: [t] }
	}), Dr.forEach((t) => {
		let n = "_onData" + Yn(t), r = e[t];
		Object.defineProperty(e, t, {
			configurable: !0,
			enumerable: !1,
			value(...t) {
				let i = r.apply(this, t);
				return e._chartjs.listeners.forEach((e) => {
					typeof e[n] == "function" && e[n](...t);
				}), i;
			}
		});
	}));
}
function kr(e, t) {
	let n = e._chartjs;
	if (!n) return;
	let r = n.listeners, i = r.indexOf(t);
	i !== -1 && r.splice(i, 1), !(r.length > 0) && (Dr.forEach((t) => {
		delete e[t];
	}), delete e._chartjs);
}
function Ar(e) {
	let t = new Set(e);
	return t.size === e.length ? e : Array.from(t);
}
var jr = function() {
	return typeof window > "u" ? function(e) {
		return e();
	} : window.requestAnimationFrame;
}();
function Mr(e, t) {
	let n = [], r = !1;
	return function(...i) {
		n = i, r || (r = !0, jr.call(window, () => {
			r = !1, e.apply(t, n);
		}));
	};
}
function Nr(e, t) {
	let n;
	return function(...r) {
		return t ? (clearTimeout(n), n = setTimeout(e, t, r)) : e.apply(this, r), t;
	};
}
var Pr = (e) => e === "start" ? "left" : e === "end" ? "right" : "center", Fr = (e, t, n) => e === "start" ? t : e === "end" ? n : (t + n) / 2;
function Ir(e, t, n) {
	let r = t.length, i = 0, a = r;
	if (e._sorted) {
		let { iScale: o, vScale: s, _parsed: c } = e, l = e.dataset && e.dataset.options ? e.dataset.options.spanGaps : null, u = o.axis, { min: d, max: f, minDefined: p, maxDefined: m } = o.getUserBounds();
		if (p) {
			if (i = Math.min(wr(c, u, d).lo, n ? r : wr(t, u, o.getPixelForValue(d)).lo), l) {
				let e = c.slice(0, i + 1).reverse().findIndex((e) => !P(e[s.axis]));
				i -= Math.max(0, e);
			}
			i = K(i, 0, r - 1);
		}
		if (m) {
			let e = Math.max(wr(c, o.axis, f, !0).hi + 1, n ? 0 : wr(t, u, o.getPixelForValue(f), !0).hi + 1);
			if (l) {
				let t = c.slice(e - 1).findIndex((e) => !P(e[s.axis]));
				e += Math.max(0, t);
			}
			a = K(e, i, r) - i;
		} else a = r - i;
	}
	return {
		start: i,
		count: a
	};
}
function Lr(e) {
	let { xScale: t, yScale: n, _scaleRanges: r } = e, i = {
		xmin: t.min,
		xmax: t.max,
		ymin: n.min,
		ymax: n.max
	};
	if (!r) return e._scaleRanges = i, !0;
	let a = r.xmin !== t.min || r.xmax !== t.max || r.ymin !== n.min || r.ymax !== n.max;
	return Object.assign(r, i), a;
}
var Rr = (e) => e === 0 || e === 1, zr = (e, t, n) => -(2 ** (10 * --e) * Math.sin((e - t) * U / n)), Br = (e, t, n) => 2 ** (-10 * e) * Math.sin((e - t) * U / n) + 1, Vr = {
	linear: (e) => e,
	easeInQuad: (e) => e * e,
	easeOutQuad: (e) => -e * (e - 2),
	easeInOutQuad: (e) => (e /= .5) < 1 ? .5 * e * e : -.5 * (--e * (e - 2) - 1),
	easeInCubic: (e) => e * e * e,
	easeOutCubic: (e) => --e * e * e + 1,
	easeInOutCubic: (e) => (e /= .5) < 1 ? .5 * e * e * e : .5 * ((e -= 2) * e * e + 2),
	easeInQuart: (e) => e * e * e * e,
	easeOutQuart: (e) => -(--e * e * e * e - 1),
	easeInOutQuart: (e) => (e /= .5) < 1 ? .5 * e * e * e * e : -.5 * ((e -= 2) * e * e * e - 2),
	easeInQuint: (e) => e * e * e * e * e,
	easeOutQuint: (e) => --e * e * e * e * e + 1,
	easeInOutQuint: (e) => (e /= .5) < 1 ? .5 * e * e * e * e * e : .5 * ((e -= 2) * e * e * e * e + 2),
	easeInSine: (e) => -Math.cos(e * W) + 1,
	easeOutSine: (e) => Math.sin(e * W),
	easeInOutSine: (e) => -.5 * (Math.cos(H * e) - 1),
	easeInExpo: (e) => e === 0 ? 0 : 2 ** (10 * (e - 1)),
	easeOutExpo: (e) => e === 1 ? 1 : -(2 ** (-10 * e)) + 1,
	easeInOutExpo: (e) => Rr(e) ? e : e < .5 ? .5 * 2 ** (10 * (e * 2 - 1)) : .5 * (-(2 ** (-10 * (e * 2 - 1))) + 2),
	easeInCirc: (e) => e >= 1 ? e : -(Math.sqrt(1 - e * e) - 1),
	easeOutCirc: (e) => Math.sqrt(1 - --e * e),
	easeInOutCirc: (e) => (e /= .5) < 1 ? -.5 * (Math.sqrt(1 - e * e) - 1) : .5 * (Math.sqrt(1 - (e -= 2) * e) + 1),
	easeInElastic: (e) => Rr(e) ? e : zr(e, .075, .3),
	easeOutElastic: (e) => Rr(e) ? e : Br(e, .075, .3),
	easeInOutElastic(e) {
		let t = .1125, n = .45;
		return Rr(e) ? e : e < .5 ? .5 * zr(e * 2, t, n) : .5 + .5 * Br(e * 2 - 1, t, n);
	},
	easeInBack(e) {
		return e * e * (2.70158 * e - 1.70158);
	},
	easeOutBack(e) {
		return --e * e * (2.70158 * e + 1.70158) + 1;
	},
	easeInOutBack(e) {
		let t = 1.70158;
		return (e /= .5) < 1 ? .5 * (e * e * (((t *= 1.525) + 1) * e - t)) : .5 * ((e -= 2) * e * (((t *= 1.525) + 1) * e + t) + 2);
	},
	easeInBounce: (e) => 1 - Vr.easeOutBounce(1 - e),
	easeOutBounce(e) {
		let t = 7.5625, n = 2.75;
		return e < 1 / n ? t * e * e : e < 2 / n ? t * (e -= 1.5 / n) * e + .75 : e < 2.5 / n ? t * (e -= 2.25 / n) * e + .9375 : t * (e -= 2.625 / n) * e + .984375;
	},
	easeInOutBounce: (e) => e < .5 ? Vr.easeInBounce(e * 2) * .5 : Vr.easeOutBounce(e * 2 - 1) * .5 + .5
};
function Hr(e) {
	if (e && typeof e == "object") {
		let t = e.toString();
		return t === "[object CanvasPattern]" || t === "[object CanvasGradient]";
	}
	return !1;
}
function Ur(e) {
	return Hr(e) ? e : new Pn(e);
}
function Wr(e) {
	return Hr(e) ? e : new Pn(e).saturate(.5).darken(.1).hexString();
}
var Gr = [
	"x",
	"y",
	"borderWidth",
	"radius",
	"tension"
], Kr = [
	"color",
	"borderColor",
	"backgroundColor"
];
function qr(e) {
	e.set("animation", {
		delay: void 0,
		duration: 1e3,
		easing: "easeOutQuart",
		fn: void 0,
		from: void 0,
		loop: void 0,
		to: void 0,
		type: void 0
	}), e.describe("animation", {
		_fallback: !1,
		_indexable: !1,
		_scriptable: (e) => e !== "onProgress" && e !== "onComplete" && e !== "fn"
	}), e.set("animations", {
		colors: {
			type: "color",
			properties: Kr
		},
		numbers: {
			type: "number",
			properties: Gr
		}
	}), e.describe("animations", { _fallback: "animation" }), e.set("transitions", {
		active: { animation: { duration: 400 } },
		resize: { animation: { duration: 0 } },
		show: { animations: {
			colors: { from: "transparent" },
			visible: {
				type: "boolean",
				duration: 0
			}
		} },
		hide: { animations: {
			colors: { to: "transparent" },
			visible: {
				type: "boolean",
				easing: "linear",
				fn: (e) => e | 0
			}
		} }
	});
}
function Jr(e) {
	e.set("layout", {
		autoPadding: !0,
		padding: {
			top: 0,
			right: 0,
			bottom: 0,
			left: 0
		}
	});
}
var Yr = /* @__PURE__ */ new Map();
function Xr(e, t) {
	t ||= {};
	let n = e + JSON.stringify(t), r = Yr.get(n);
	return r || (r = new Intl.NumberFormat(e, t), Yr.set(n, r)), r;
}
function Zr(e, t, n) {
	return Xr(t, n).format(e);
}
var Qr = {
	values(e) {
		return F(e) ? e : "" + e;
	},
	numeric(e, t, n) {
		if (e === 0) return "0";
		let r = this.chart.options.locale, i, a = e;
		if (n.length > 1) {
			let t = Math.max(Math.abs(n[0].value), Math.abs(n[n.length - 1].value));
			(t < 1e-4 || t > 0x38d7ea4c68000) && (i = "scientific"), a = $r(e, n);
		}
		let o = ar(Math.abs(a)), s = isNaN(o) ? 1 : Math.max(Math.min(-1 * Math.floor(o), 20), 0), c = {
			notation: i,
			minimumFractionDigits: s,
			maximumFractionDigits: s
		};
		return Object.assign(c, this.options.ticks.format), Zr(e, r, c);
	},
	logarithmic(e, t, n) {
		if (e === 0) return "0";
		let r = n[t].significand || e / 10 ** Math.floor(ar(e));
		return [
			1,
			2,
			3,
			5,
			10,
			15
		].includes(r) || t > .8 * n.length ? Qr.numeric.call(this, e, t, n) : "";
	}
};
function $r(e, t) {
	let n = t.length > 3 ? t[2].value - t[1].value : t[1].value - t[0].value;
	return Math.abs(n) >= 1 && e !== Math.floor(e) && (n = e - Math.floor(e)), n;
}
var ei = { formatters: Qr };
function ti(e) {
	e.set("scale", {
		display: !0,
		offset: !1,
		reverse: !1,
		beginAtZero: !1,
		bounds: "ticks",
		clip: !0,
		grace: 0,
		grid: {
			display: !0,
			lineWidth: 1,
			drawOnChartArea: !0,
			drawTicks: !0,
			tickLength: 8,
			tickWidth: (e, t) => t.lineWidth,
			tickColor: (e, t) => t.color,
			offset: !1
		},
		border: {
			display: !0,
			dash: [],
			dashOffset: 0,
			width: 1
		},
		title: {
			display: !1,
			text: "",
			padding: {
				top: 4,
				bottom: 4
			}
		},
		ticks: {
			minRotation: 0,
			maxRotation: 50,
			mirror: !1,
			textStrokeWidth: 0,
			textStrokeColor: "",
			padding: 3,
			display: !0,
			autoSkip: !0,
			autoSkipPadding: 3,
			labelOffset: 0,
			callback: ei.formatters.values,
			minor: {},
			major: {},
			align: "center",
			crossAlign: "near",
			showLabelBackdrop: !1,
			backdropColor: "rgba(255, 255, 255, 0.75)",
			backdropPadding: 2
		}
	}), e.route("scale.ticks", "color", "", "color"), e.route("scale.grid", "color", "", "borderColor"), e.route("scale.border", "color", "", "borderColor"), e.route("scale.title", "color", "", "color"), e.describe("scale", {
		_fallback: !1,
		_scriptable: (e) => !e.startsWith("before") && !e.startsWith("after") && e !== "callback" && e !== "parser",
		_indexable: (e) => e !== "borderDash" && e !== "tickBorderDash" && e !== "dash"
	}), e.describe("scales", { _fallback: "scale" }), e.describe("scale.ticks", {
		_scriptable: (e) => e !== "backdropPadding" && e !== "callback",
		_indexable: (e) => e !== "backdropPadding"
	});
}
var ni = Object.create(null), ri = Object.create(null);
function ii(e, t) {
	if (!t) return e;
	let n = t.split(".");
	for (let t = 0, r = n.length; t < r; ++t) {
		let r = n[t];
		e = e[r] || (e[r] = Object.create(null));
	}
	return e;
}
function ai(e, t, n) {
	return typeof t == "string" ? Hn(ii(e, t), n) : Hn(ii(e, ""), t);
}
var q = /* #__PURE__ */ new class {
	constructor(e, t) {
		this.animation = void 0, this.backgroundColor = "rgba(0,0,0,0.1)", this.borderColor = "rgba(0,0,0,0.1)", this.color = "#666", this.datasets = {}, this.devicePixelRatio = (e) => e.chart.platform.getDevicePixelRatio(), this.elements = {}, this.events = [
			"mousemove",
			"mouseout",
			"click",
			"touchstart",
			"touchmove"
		], this.font = {
			family: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif",
			size: 12,
			style: "normal",
			lineHeight: 1.2,
			weight: null
		}, this.hover = {}, this.hoverBackgroundColor = (e, t) => Wr(t.backgroundColor), this.hoverBorderColor = (e, t) => Wr(t.borderColor), this.hoverColor = (e, t) => Wr(t.color), this.indexAxis = "x", this.interaction = {
			mode: "nearest",
			intersect: !0,
			includeInvisible: !1
		}, this.maintainAspectRatio = !0, this.onHover = null, this.onClick = null, this.parsing = !0, this.plugins = {}, this.responsive = !0, this.scale = void 0, this.scales = {}, this.showLine = !0, this.drawActiveElementsOnTop = !0, this.describe(e), this.apply(t);
	}
	set(e, t) {
		return ai(this, e, t);
	}
	get(e) {
		return ii(this, e);
	}
	describe(e, t) {
		return ai(ri, e, t);
	}
	override(e, t) {
		return ai(ni, e, t);
	}
	route(e, t, n, r) {
		let i = ii(this, e), a = ii(this, n), o = "_" + t;
		Object.defineProperties(i, {
			[o]: {
				value: i[t],
				writable: !0
			},
			[t]: {
				enumerable: !0,
				get() {
					let e = this[o], t = a[r];
					return I(e) ? Object.assign({}, t, e) : z(e, t);
				},
				set(e) {
					this[o] = e;
				}
			}
		});
	}
	apply(e) {
		e.forEach((e) => e(this));
	}
}({
	_scriptable: (e) => !e.startsWith("on"),
	_indexable: (e) => e !== "events",
	hover: { _fallback: "interaction" },
	interaction: {
		_scriptable: !1,
		_indexable: !1
	}
}, [
	qr,
	Jr,
	ti
]);
function oi(e) {
	return !e || P(e.size) || P(e.family) ? null : (e.style ? e.style + " " : "") + (e.weight ? e.weight + " " : "") + e.size + "px " + e.family;
}
function si(e, t, n, r, i) {
	let a = t[i];
	return a || (a = t[i] = e.measureText(i).width, n.push(i)), a > r && (r = a), r;
}
function ci(e, t, n, r) {
	r ||= {};
	let i = r.data = r.data || {}, a = r.garbageCollect = r.garbageCollect || [];
	r.font !== t && (i = r.data = {}, a = r.garbageCollect = [], r.font = t), e.save(), e.font = t;
	let o = 0, s = n.length, c, l, u, d, f;
	for (c = 0; c < s; c++) if (d = n[c], d != null && !F(d)) o = si(e, i, a, o, d);
	else if (F(d)) for (l = 0, u = d.length; l < u; l++) f = d[l], f != null && !F(f) && (o = si(e, i, a, o, f));
	e.restore();
	let p = a.length / 2;
	if (p > n.length) {
		for (c = 0; c < p; c++) delete i[a[c]];
		a.splice(0, p);
	}
	return o;
}
function li(e, t, n) {
	let r = e.currentDevicePixelRatio, i = n === 0 ? 0 : Math.max(n / 2, .5);
	return Math.round((t - i) * r) / r + i;
}
function ui(e, t) {
	(t || e) && (t ||= e.getContext("2d"), t.save(), t.resetTransform(), t.clearRect(0, 0, e.width, e.height), t.restore());
}
function di(e, t, n, r) {
	fi(e, t, n, r, null);
}
function fi(e, t, n, r, i) {
	let a, o, s, c, l, u, d, f, p = t.pointStyle, m = t.rotation, h = t.radius, g = (m || 0) * nr;
	if (p && typeof p == "object" && (a = p.toString(), a === "[object HTMLImageElement]" || a === "[object HTMLCanvasElement]")) e.save(), e.translate(n, r), e.rotate(g), e.drawImage(p, -p.width / 2, -p.height / 2, p.width, p.height), e.restore();
	else if (!(isNaN(h) || h <= 0)) {
		switch (e.beginPath(), p) {
			default:
				i ? e.ellipse(n, r, i / 2, h, 0, 0, U) : e.arc(n, r, h, 0, U), e.closePath();
				break;
			case "triangle":
				u = i ? i / 2 : h, e.moveTo(n + Math.sin(g) * u, r - Math.cos(g) * h), g += ir, e.lineTo(n + Math.sin(g) * u, r - Math.cos(g) * h), g += ir, e.lineTo(n + Math.sin(g) * u, r - Math.cos(g) * h), e.closePath();
				break;
			case "rectRounded":
				l = h * .516, c = h - l, o = Math.cos(g + rr) * c, d = Math.cos(g + rr) * (i ? i / 2 - l : c), s = Math.sin(g + rr) * c, f = Math.sin(g + rr) * (i ? i / 2 - l : c), e.arc(n - d, r - s, l, g - H, g - W), e.arc(n + f, r - o, l, g - W, g), e.arc(n + d, r + s, l, g, g + W), e.arc(n - f, r + o, l, g + W, g + H), e.closePath();
				break;
			case "rect":
				if (!m) {
					c = Math.SQRT1_2 * h, u = i ? i / 2 : c, e.rect(n - u, r - c, 2 * u, 2 * c);
					break;
				}
				g += rr;
			case "rectRot":
				d = Math.cos(g) * (i ? i / 2 : h), o = Math.cos(g) * h, s = Math.sin(g) * h, f = Math.sin(g) * (i ? i / 2 : h), e.moveTo(n - d, r - s), e.lineTo(n + f, r - o), e.lineTo(n + d, r + s), e.lineTo(n - f, r + o), e.closePath();
				break;
			case "crossRot": g += rr;
			case "cross":
				d = Math.cos(g) * (i ? i / 2 : h), o = Math.cos(g) * h, s = Math.sin(g) * h, f = Math.sin(g) * (i ? i / 2 : h), e.moveTo(n - d, r - s), e.lineTo(n + d, r + s), e.moveTo(n + f, r - o), e.lineTo(n - f, r + o);
				break;
			case "star":
				d = Math.cos(g) * (i ? i / 2 : h), o = Math.cos(g) * h, s = Math.sin(g) * h, f = Math.sin(g) * (i ? i / 2 : h), e.moveTo(n - d, r - s), e.lineTo(n + d, r + s), e.moveTo(n + f, r - o), e.lineTo(n - f, r + o), g += rr, d = Math.cos(g) * (i ? i / 2 : h), o = Math.cos(g) * h, s = Math.sin(g) * h, f = Math.sin(g) * (i ? i / 2 : h), e.moveTo(n - d, r - s), e.lineTo(n + d, r + s), e.moveTo(n + f, r - o), e.lineTo(n - f, r + o);
				break;
			case "line":
				o = i ? i / 2 : Math.cos(g) * h, s = Math.sin(g) * h, e.moveTo(n - o, r - s), e.lineTo(n + o, r + s);
				break;
			case "dash":
				e.moveTo(n, r), e.lineTo(n + Math.cos(g) * (i ? i / 2 : h), r + Math.sin(g) * h);
				break;
			case !1: e.closePath();
		}
		e.fill(), t.borderWidth > 0 && e.stroke();
	}
}
function pi(e, t, n) {
	return n ||= .5, !t || e && e.x > t.left - n && e.x < t.right + n && e.y > t.top - n && e.y < t.bottom + n;
}
function mi(e, t) {
	e.save(), e.beginPath(), e.rect(t.left, t.top, t.right - t.left, t.bottom - t.top), e.clip();
}
function hi(e) {
	e.restore();
}
function gi(e, t, n, r, i) {
	if (!t) return e.lineTo(n.x, n.y);
	if (i === "middle") {
		let r = (t.x + n.x) / 2;
		e.lineTo(r, t.y), e.lineTo(r, n.y);
	} else i === "after" == !!r ? e.lineTo(n.x, t.y) : e.lineTo(t.x, n.y);
	e.lineTo(n.x, n.y);
}
function _i(e, t, n, r) {
	if (!t) return e.lineTo(n.x, n.y);
	e.bezierCurveTo(r ? t.cp1x : t.cp2x, r ? t.cp1y : t.cp2y, r ? n.cp2x : n.cp1x, r ? n.cp2y : n.cp1y, n.x, n.y);
}
function vi(e, t) {
	t.translation && e.translate(t.translation[0], t.translation[1]), P(t.rotation) || e.rotate(t.rotation), t.color && (e.fillStyle = t.color), t.textAlign && (e.textAlign = t.textAlign), t.textBaseline && (e.textBaseline = t.textBaseline);
}
function yi(e, t, n, r, i) {
	if (i.strikethrough || i.underline) {
		let a = e.measureText(r), o = t - a.actualBoundingBoxLeft, s = t + a.actualBoundingBoxRight, c = n - a.actualBoundingBoxAscent, l = n + a.actualBoundingBoxDescent, u = i.strikethrough ? (c + l) / 2 : l;
		e.strokeStyle = e.fillStyle, e.beginPath(), e.lineWidth = i.decorationWidth || 2, e.moveTo(o, u), e.lineTo(s, u), e.stroke();
	}
}
function bi(e, t) {
	let n = e.fillStyle;
	e.fillStyle = t.color, e.fillRect(t.left, t.top, t.width, t.height), e.fillStyle = n;
}
function xi(e, t, n, r, i, a = {}) {
	let o = F(t) ? t : [t], s = a.strokeWidth > 0 && a.strokeColor !== "", c, l;
	for (e.save(), e.font = i.string, vi(e, a), c = 0; c < o.length; ++c) l = o[c], a.backdrop && bi(e, a.backdrop), s && (a.strokeColor && (e.strokeStyle = a.strokeColor), P(a.strokeWidth) || (e.lineWidth = a.strokeWidth), e.strokeText(l, n, r, a.maxWidth)), e.fillText(l, n, r, a.maxWidth), yi(e, n, r, l, a), r += Number(i.lineHeight);
	e.restore();
}
function Si(e, t) {
	let { x: n, y: r, w: i, h: a, radius: o } = t;
	e.arc(n + o.topLeft, r + o.topLeft, o.topLeft, 1.5 * H, H, !0), e.lineTo(n, r + a - o.bottomLeft), e.arc(n + o.bottomLeft, r + a - o.bottomLeft, o.bottomLeft, H, W, !0), e.lineTo(n + i - o.bottomRight, r + a), e.arc(n + i - o.bottomRight, r + a - o.bottomRight, o.bottomRight, W, 0, !0), e.lineTo(n + i, r + o.topRight), e.arc(n + i - o.topRight, r + o.topRight, o.topRight, 0, -W, !0), e.lineTo(n + o.topLeft, r);
}
var Ci = /^(normal|(\d+(?:\.\d+)?)(px|em|%)?)$/, wi = /^(normal|italic|initial|inherit|unset|(oblique( -?[0-9]?[0-9]deg)?))$/;
function Ti(e, t) {
	let n = ("" + e).match(Ci);
	if (!n || n[1] === "normal") return t * 1.2;
	switch (e = +n[2], n[3]) {
		case "px": return e;
		case "%": e /= 100;
	}
	return t * e;
}
var Ei = (e) => +e || 0;
function Di(e, t) {
	let n = {}, r = I(t), i = r ? Object.keys(t) : t, a = I(e) ? r ? (n) => z(e[n], e[t[n]]) : (t) => e[t] : () => e;
	for (let e of i) n[e] = Ei(a(e));
	return n;
}
function Oi(e) {
	return Di(e, {
		top: "y",
		right: "x",
		bottom: "y",
		left: "x"
	});
}
function ki(e) {
	return Di(e, [
		"topLeft",
		"topRight",
		"bottomLeft",
		"bottomRight"
	]);
}
function J(e) {
	let t = Oi(e);
	return t.width = t.left + t.right, t.height = t.top + t.bottom, t;
}
function Y(e, t) {
	e ||= {}, t ||= q.font;
	let n = z(e.size, t.size);
	typeof n == "string" && (n = parseInt(n, 10));
	let r = z(e.style, t.style);
	r && !("" + r).match(wi) && (console.warn("Invalid font style specified: \"" + r + "\""), r = void 0);
	let i = {
		family: z(e.family, t.family),
		lineHeight: Ti(z(e.lineHeight, t.lineHeight), n),
		size: n,
		style: r,
		weight: z(e.weight, t.weight),
		string: ""
	};
	return i.string = oi(i), i;
}
function Ai(e, t, n, r) {
	let i = !0, a, o, s;
	for (a = 0, o = e.length; a < o; ++a) if (s = e[a], s !== void 0 && (t !== void 0 && typeof s == "function" && (s = s(t), i = !1), n !== void 0 && F(s) && (s = s[n % s.length], i = !1), s !== void 0)) return r && !i && (r.cacheable = !1), s;
}
function ji(e, t, n) {
	let { min: r, max: i } = e, a = Ln(t, (i - r) / 2), o = (e, t) => n && e === 0 ? 0 : e + t;
	return {
		min: o(r, -Math.abs(a)),
		max: o(i, a)
	};
}
function Mi(e, t) {
	return Object.assign(Object.create(e), t);
}
function Ni(e, t = [""], n, r, i = () => e[0]) {
	let a = n || e;
	return r === void 0 && (r = Yi("_fallback", e)), new Proxy({
		[Symbol.toStringTag]: "Object",
		_cacheable: !0,
		_scopes: e,
		_rootScopes: a,
		_fallback: r,
		_getTarget: i,
		override: (n) => Ni([n, ...e], t, a, r)
	}, {
		deleteProperty(t, n) {
			return delete t[n], delete t._keys, delete e[0][n], !0;
		},
		get(n, r) {
			return Ri(n, r, () => Ji(r, t, e, n));
		},
		getOwnPropertyDescriptor(e, t) {
			return Reflect.getOwnPropertyDescriptor(e._scopes[0], t);
		},
		getPrototypeOf() {
			return Reflect.getPrototypeOf(e[0]);
		},
		has(e, t) {
			return Xi(e).includes(t);
		},
		ownKeys(e) {
			return Xi(e);
		},
		set(e, t, n) {
			let r = e._storage ||= i();
			return e[t] = r[t] = n, delete e._keys, !0;
		}
	});
}
function Pi(e, t, n, r) {
	let i = {
		_cacheable: !1,
		_proxy: e,
		_context: t,
		_subProxy: n,
		_stack: /* @__PURE__ */ new Set(),
		_descriptors: Fi(e, r),
		setContext: (t) => Pi(e, t, n, r),
		override: (i) => Pi(e.override(i), t, n, r)
	};
	return new Proxy(i, {
		deleteProperty(t, n) {
			return delete t[n], delete e[n], !0;
		},
		get(e, t, n) {
			return Ri(e, t, () => zi(e, t, n));
		},
		getOwnPropertyDescriptor(t, n) {
			return t._descriptors.allKeys ? Reflect.has(e, n) ? {
				enumerable: !0,
				configurable: !0
			} : void 0 : Reflect.getOwnPropertyDescriptor(e, n);
		},
		getPrototypeOf() {
			return Reflect.getPrototypeOf(e);
		},
		has(t, n) {
			return Reflect.has(e, n);
		},
		ownKeys() {
			return Reflect.ownKeys(e);
		},
		set(t, n, r) {
			return e[n] = r, delete t[n], !0;
		}
	});
}
function Fi(e, t = {
	scriptable: !0,
	indexable: !0
}) {
	let { _scriptable: n = t.scriptable, _indexable: r = t.indexable, _allKeys: i = t.allKeys } = e;
	return {
		allKeys: i,
		scriptable: n,
		indexable: r,
		isScriptable: Zn(n) ? n : () => n,
		isIndexable: Zn(r) ? r : () => r
	};
}
var Ii = (e, t) => e ? e + Yn(t) : t, Li = (e, t) => I(t) && e !== "adapters" && (Object.getPrototypeOf(t) === null || t.constructor === Object);
function Ri(e, t, n) {
	if (Object.prototype.hasOwnProperty.call(e, t) || t === "constructor") return e[t];
	let r = n();
	return e[t] = r, r;
}
function zi(e, t, n) {
	let { _proxy: r, _context: i, _subProxy: a, _descriptors: o } = e, s = r[t];
	return Zn(s) && o.isScriptable(t) && (s = Bi(t, s, e, n)), F(s) && s.length && (s = Vi(t, s, e, o.isIndexable)), Li(t, s) && (s = Pi(s, i, a && a[t], o)), s;
}
function Bi(e, t, n, r) {
	let { _proxy: i, _context: a, _subProxy: o, _stack: s } = n;
	if (s.has(e)) throw Error("Recursion detected: " + Array.from(s).join("->") + "->" + e);
	s.add(e);
	let c = t(a, o || r);
	return s.delete(e), Li(e, c) && (c = Gi(i._scopes, i, e, c)), c;
}
function Vi(e, t, n, r) {
	let { _proxy: i, _context: a, _subProxy: o, _descriptors: s } = n;
	if (a.index !== void 0 && r(e)) return t[a.index % t.length];
	if (I(t[0])) {
		let n = t, r = i._scopes.filter((e) => e !== n);
		t = [];
		for (let c of n) {
			let n = Gi(r, i, e, c);
			t.push(Pi(n, a, o && o[e], s));
		}
	}
	return t;
}
function Hi(e, t, n) {
	return Zn(e) ? e(t, n) : e;
}
var Ui = (e, t) => e === !0 ? t : typeof e == "string" ? Jn(t, e) : void 0;
function Wi(e, t, n, r, i) {
	for (let a of t) {
		let t = Ui(n, a);
		if (t) {
			e.add(t);
			let a = Hi(t._fallback, n, i);
			if (a !== void 0 && a !== n && a !== r) return a;
		} else if (t === !1 && r !== void 0 && n !== r) return null;
	}
	return !1;
}
function Gi(e, t, n, r) {
	let i = t._rootScopes, a = Hi(t._fallback, n, r), o = [...e, ...i], s = /* @__PURE__ */ new Set();
	s.add(r);
	let c = Ki(s, o, n, a || n, r);
	return c === null || a !== void 0 && a !== n && (c = Ki(s, o, a, c, r), c === null) ? !1 : Ni(Array.from(s), [""], i, a, () => qi(t, n, r));
}
function Ki(e, t, n, r, i) {
	for (; n;) n = Wi(e, t, n, r, i);
	return n;
}
function qi(e, t, n) {
	let r = e._getTarget();
	t in r || (r[t] = {});
	let i = r[t];
	return F(i) && I(n) ? n : i || {};
}
function Ji(e, t, n, r) {
	let i;
	for (let a of t) if (i = Yi(Ii(a, e), n), i !== void 0) return Li(e, i) ? Gi(n, r, e, i) : i;
}
function Yi(e, t) {
	for (let n of t) {
		if (!n) continue;
		let t = n[e];
		if (t !== void 0) return t;
	}
}
function Xi(e) {
	let t = e._keys;
	return t ||= e._keys = Zi(e._scopes), t;
}
function Zi(e) {
	let t = /* @__PURE__ */ new Set();
	for (let n of e) for (let e of Object.keys(n).filter((e) => !e.startsWith("_"))) t.add(e);
	return Array.from(t);
}
var Qi = 2 ** -52 || 1e-14, $i = (e, t) => t < e.length && !e[t].skip && e[t], ea = (e) => e === "x" ? "y" : "x";
function ta(e, t, n, r) {
	let i = e.skip ? t : e, a = t, o = n.skip ? t : n, s = vr(a, i), c = vr(o, a), l = s / (s + c), u = c / (s + c);
	l = isNaN(l) ? 0 : l, u = isNaN(u) ? 0 : u;
	let d = r * l, f = r * u;
	return {
		previous: {
			x: a.x - d * (o.x - i.x),
			y: a.y - d * (o.y - i.y)
		},
		next: {
			x: a.x + f * (o.x - i.x),
			y: a.y + f * (o.y - i.y)
		}
	};
}
function na(e, t, n) {
	let r = e.length, i, a, o, s, c, l = $i(e, 0);
	for (let u = 0; u < r - 1; ++u) c = l, l = $i(e, u + 1), c && l && (sr(t[u], 0, Qi) ? n[u] = n[u + 1] = 0 : (i = n[u] / t[u], a = n[u + 1] / t[u], s = i ** 2 + a ** 2, !(s <= 9) && (o = 3 / Math.sqrt(s), n[u] = i * o * t[u], n[u + 1] = a * o * t[u])));
}
function ra(e, t, n = "x") {
	let r = ea(n), i = e.length, a, o, s, c = $i(e, 0);
	for (let l = 0; l < i; ++l) {
		if (o = s, s = c, c = $i(e, l + 1), !s) continue;
		let i = s[n], u = s[r];
		o && (a = (i - o[n]) / 3, s[`cp1${n}`] = i - a, s[`cp1${r}`] = u - a * t[l]), c && (a = (c[n] - i) / 3, s[`cp2${n}`] = i + a, s[`cp2${r}`] = u + a * t[l]);
	}
}
function ia(e, t = "x") {
	let n = ea(t), r = e.length, i = Array(r).fill(0), a = Array(r), o, s, c, l = $i(e, 0);
	for (o = 0; o < r; ++o) if (s = c, c = l, l = $i(e, o + 1), c) {
		if (l) {
			let e = l[t] - c[t];
			i[o] = e === 0 ? 0 : (l[n] - c[n]) / e;
		}
		a[o] = s ? l ? or(i[o - 1]) === or(i[o]) ? (i[o - 1] + i[o]) / 2 : 0 : i[o - 1] : i[o];
	}
	na(e, i, a), ra(e, a, t);
}
function aa(e, t, n) {
	return Math.max(Math.min(e, n), t);
}
function oa(e, t) {
	let n, r, i, a, o, s = pi(e[0], t);
	for (n = 0, r = e.length; n < r; ++n) o = a, a = s, s = n < r - 1 && pi(e[n + 1], t), a && (i = e[n], o && (i.cp1x = aa(i.cp1x, t.left, t.right), i.cp1y = aa(i.cp1y, t.top, t.bottom)), s && (i.cp2x = aa(i.cp2x, t.left, t.right), i.cp2y = aa(i.cp2y, t.top, t.bottom)));
}
function sa(e, t, n, r, i) {
	let a, o, s, c;
	if (t.spanGaps && (e = e.filter((e) => !e.skip)), t.cubicInterpolationMode === "monotone") ia(e, i);
	else {
		let n = r ? e[e.length - 1] : e[0];
		for (a = 0, o = e.length; a < o; ++a) s = e[a], c = ta(n, s, e[Math.min(a + 1, o - +!r) % o], t.tension), s.cp1x = c.previous.x, s.cp1y = c.previous.y, s.cp2x = c.next.x, s.cp2y = c.next.y, n = s;
	}
	t.capBezierPoints && oa(e, n);
}
function ca() {
	return typeof window < "u" && typeof document < "u";
}
function la(e) {
	let t = e.parentNode;
	return t && t.toString() === "[object ShadowRoot]" && (t = t.host), t;
}
function ua(e, t, n) {
	let r;
	return typeof e == "string" ? (r = parseInt(e, 10), e.indexOf("%") !== -1 && (r = r / 100 * t.parentNode[n])) : r = e, r;
}
var da = (e) => e.ownerDocument.defaultView.getComputedStyle(e, null);
function fa(e, t) {
	return da(e).getPropertyValue(t);
}
var pa = [
	"top",
	"right",
	"bottom",
	"left"
];
function ma(e, t, n) {
	let r = {};
	n = n ? "-" + n : "";
	for (let i = 0; i < 4; i++) {
		let a = pa[i];
		r[a] = parseFloat(e[t + "-" + a + n]) || 0;
	}
	return r.width = r.left + r.right, r.height = r.top + r.bottom, r;
}
var ha = (e, t, n) => (e > 0 || t > 0) && (!n || !n.shadowRoot);
function ga(e, t) {
	let n = e.touches, r = n && n.length ? n[0] : e, { offsetX: i, offsetY: a } = r, o = !1, s, c;
	if (ha(i, a, e.target)) s = i, c = a;
	else {
		let e = t.getBoundingClientRect();
		s = r.clientX - e.left, c = r.clientY - e.top, o = !0;
	}
	return {
		x: s,
		y: c,
		box: o
	};
}
function _a(e, t) {
	if ("native" in e) return e;
	let { canvas: n, currentDevicePixelRatio: r } = t, i = da(n), a = i.boxSizing === "border-box", o = ma(i, "padding"), s = ma(i, "border", "width"), { x: c, y: l, box: u } = ga(e, n), d = o.left + (u && s.left), f = o.top + (u && s.top), { width: p, height: m } = t;
	return a && (p -= o.width + s.width, m -= o.height + s.height), {
		x: Math.round((c - d) / p * n.width / r),
		y: Math.round((l - f) / m * n.height / r)
	};
}
function va(e, t, n) {
	let r, i;
	if (t === void 0 || n === void 0) {
		let a = e && la(e);
		if (!a) t = e.clientWidth, n = e.clientHeight;
		else {
			let e = a.getBoundingClientRect(), o = da(a), s = ma(o, "border", "width"), c = ma(o, "padding");
			t = e.width - c.width - s.width, n = e.height - c.height - s.height, r = ua(o.maxWidth, a, "clientWidth"), i = ua(o.maxHeight, a, "clientHeight");
		}
	}
	return {
		width: t,
		height: n,
		maxWidth: r || tr,
		maxHeight: i || tr
	};
}
var ya = (e) => Math.round(e * 10) / 10;
function ba(e, t, n, r) {
	let i = da(e), a = ma(i, "margin"), o = ua(i.maxWidth, e, "clientWidth") || tr, s = ua(i.maxHeight, e, "clientHeight") || tr, c = va(e, t, n), { width: l, height: u } = c;
	if (i.boxSizing === "content-box") {
		let e = ma(i, "border", "width"), t = ma(i, "padding");
		l -= t.width + e.width, u -= t.height + e.height;
	}
	return l = Math.max(0, l - a.width), u = Math.max(0, r ? l / r : u - a.height), l = ya(Math.min(l, o, c.maxWidth)), u = ya(Math.min(u, s, c.maxHeight)), l && !u && (u = ya(l / 2)), (t !== void 0 || n !== void 0) && r && c.height && u > c.height && (u = c.height, l = ya(Math.floor(u * r))), {
		width: l,
		height: u
	};
}
function xa(e, t, n) {
	let r = t || 1, i = ya(e.height * r), a = ya(e.width * r);
	e.height = ya(e.height), e.width = ya(e.width);
	let o = e.canvas;
	o.style && (n || !o.style.height && !o.style.width) && (o.style.height = `${e.height}px`, o.style.width = `${e.width}px`);
	let s = Math.floor(i), c = Math.floor(a);
	return e.currentDevicePixelRatio !== r || o.height !== s || o.width !== c ? (e.currentDevicePixelRatio = r, o.height = s, o.width = c, e.ctx.setTransform(r, 0, 0, r, 0, 0), !0) : !1;
}
var Sa = function() {
	let e = !1;
	try {
		let t = { get passive() {
			return e = !0, !1;
		} };
		ca() && (window.addEventListener("test", null, t), window.removeEventListener("test", null, t));
	} catch {}
	return e;
}();
function Ca(e, t) {
	let n = fa(e, t), r = n && n.match(/^(\d+)(\.\d+)?px$/);
	return r ? +r[1] : void 0;
}
function wa(e, t, n, r) {
	return {
		x: e.x + n * (t.x - e.x),
		y: e.y + n * (t.y - e.y)
	};
}
function Ta(e, t, n, r) {
	return {
		x: e.x + n * (t.x - e.x),
		y: r === "middle" ? n < .5 ? e.y : t.y : r === "after" ? n < 1 ? e.y : t.y : n > 0 ? t.y : e.y
	};
}
function Ea(e, t, n, r) {
	let i = {
		x: e.cp2x,
		y: e.cp2y
	}, a = {
		x: t.cp1x,
		y: t.cp1y
	}, o = wa(e, i, n), s = wa(i, a, n), c = wa(a, t, n);
	return wa(wa(o, s, n), wa(s, c, n), n);
}
var Da = function(e, t) {
	return {
		x(n) {
			return e + e + t - n;
		},
		setWidth(e) {
			t = e;
		},
		textAlign(e) {
			return e === "center" ? e : e === "right" ? "left" : "right";
		},
		xPlus(e, t) {
			return e - t;
		},
		leftForLtr(e, t) {
			return e - t;
		}
	};
}, Oa = function() {
	return {
		x(e) {
			return e;
		},
		setWidth(e) {},
		textAlign(e) {
			return e;
		},
		xPlus(e, t) {
			return e + t;
		},
		leftForLtr(e, t) {
			return e;
		}
	};
};
function ka(e, t, n) {
	return e ? Da(t, n) : Oa();
}
function Aa(e, t) {
	let n, r;
	(t === "ltr" || t === "rtl") && (n = e.canvas.style, r = [n.getPropertyValue("direction"), n.getPropertyPriority("direction")], n.setProperty("direction", t, "important"), e.prevTextDirection = r);
}
function ja(e, t) {
	t !== void 0 && (delete e.prevTextDirection, e.canvas.style.setProperty("direction", t[0], t[1]));
}
function Ma(e) {
	return e === "angle" ? {
		between: br,
		compare: yr,
		normalize: G
	} : {
		between: Sr,
		compare: (e, t) => e - t,
		normalize: (e) => e
	};
}
function Na({ start: e, end: t, count: n, loop: r, style: i }) {
	return {
		start: e % n,
		end: t % n,
		loop: r && (t - e + 1) % n === 0,
		style: i
	};
}
function Pa(e, t, n) {
	let { property: r, start: i, end: a } = n, { between: o, normalize: s } = Ma(r), c = t.length, { start: l, end: u, loop: d } = e, f, p;
	if (d) {
		for (l += c, u += c, f = 0, p = c; f < p && o(s(t[l % c][r]), i, a); ++f) l--, u--;
		l %= c, u %= c;
	}
	return u < l && (u += c), {
		start: l,
		end: u,
		loop: d,
		style: e.style
	};
}
function Fa(e, t, n) {
	if (!n) return [e];
	let { property: r, start: i, end: a } = n, o = t.length, { compare: s, between: c, normalize: l } = Ma(r), { start: u, end: d, loop: f, style: p } = Pa(e, t, n), m = [], h = !1, g = null, _, v, y, b = () => c(i, y, _) && s(i, y) !== 0, x = () => s(a, _) === 0 || c(a, y, _), S = () => h || b(), C = () => !h || x();
	for (let e = u, n = u; e <= d; ++e) v = t[e % o], !v.skip && (_ = l(v[r]), _ !== y && (h = c(_, i, a), g === null && S() && (g = s(_, i) === 0 ? e : n), g !== null && C() && (m.push(Na({
		start: g,
		end: e,
		loop: f,
		count: o,
		style: p
	})), g = null), n = e, y = _));
	return g !== null && m.push(Na({
		start: g,
		end: d,
		loop: f,
		count: o,
		style: p
	})), m;
}
function Ia(e, t) {
	let n = [], r = e.segments;
	for (let i = 0; i < r.length; i++) {
		let a = Fa(r[i], e.points, t);
		a.length && n.push(...a);
	}
	return n;
}
function La(e, t, n, r) {
	let i = 0, a = t - 1;
	if (n && !r) for (; i < t && !e[i].skip;) i++;
	for (; i < t && e[i].skip;) i++;
	for (i %= t, n && (a += i); a > i && e[a % t].skip;) a--;
	return a %= t, {
		start: i,
		end: a
	};
}
function Ra(e, t, n, r) {
	let i = e.length, a = [], o = t, s = e[t], c;
	for (c = t + 1; c <= n; ++c) {
		let n = e[c % i];
		n.skip || n.stop ? s.skip || (r = !1, a.push({
			start: t % i,
			end: (c - 1) % i,
			loop: r
		}), t = o = n.stop ? c : null) : (o = c, s.skip && (t = c)), s = n;
	}
	return o !== null && a.push({
		start: t % i,
		end: o % i,
		loop: r
	}), a;
}
function za(e, t) {
	let n = e.points, r = e.options.spanGaps, i = n.length;
	if (!i) return [];
	let a = !!e._loop, { start: o, end: s } = La(n, i, a, r);
	return r === !0 ? Ba(e, [{
		start: o,
		end: s,
		loop: a
	}], n, t) : Ba(e, Ra(n, o, s < o ? s + i : s, !!e._fullLoop && o === 0 && s === i - 1), n, t);
}
function Ba(e, t, n, r) {
	return !r || !r.setContext || !n ? t : Va(e, t, n, r);
}
function Va(e, t, n, r) {
	let i = e._chart.getContext(), a = Ha(e.options), { _datasetIndex: o, options: { spanGaps: s } } = e, c = n.length, l = [], u = a, d = t[0].start, f = d;
	function p(e, t, r, i) {
		let a = s ? -1 : 1;
		if (e !== t) {
			for (e += c; n[e % c].skip;) e -= a;
			for (; n[t % c].skip;) t += a;
			e % c !== t % c && (l.push({
				start: e % c,
				end: t % c,
				loop: r,
				style: i
			}), u = i, d = t % c);
		}
	}
	for (let e of t) {
		d = s ? d : e.start;
		let t = n[d % c], a;
		for (f = d + 1; f <= e.end; f++) {
			let s = n[f % c];
			a = Ha(r.setContext(Mi(i, {
				type: "segment",
				p0: t,
				p1: s,
				p0DataIndex: (f - 1) % c,
				p1DataIndex: f % c,
				datasetIndex: o
			}))), Ua(a, u) && p(d, f - 1, e.loop, u), t = s, u = a;
		}
		d < f - 1 && p(d, f - 1, e.loop, u);
	}
	return l;
}
function Ha(e) {
	return {
		backgroundColor: e.backgroundColor,
		borderCapStyle: e.borderCapStyle,
		borderDash: e.borderDash,
		borderDashOffset: e.borderDashOffset,
		borderJoinStyle: e.borderJoinStyle,
		borderWidth: e.borderWidth,
		borderColor: e.borderColor
	};
}
function Ua(e, t) {
	if (!t) return !1;
	let n = [], r = function(e, t) {
		return Hr(t) ? (n.includes(t) || n.push(t), n.indexOf(t)) : t;
	};
	return JSON.stringify(e, r) !== JSON.stringify(t, r);
}
function Wa(e, t, n) {
	return e.options.clip ? e[n] : t[n];
}
function Ga(e, t) {
	let { xScale: n, yScale: r } = e;
	return n && r ? {
		left: Wa(n, t, "left"),
		right: Wa(n, t, "right"),
		top: Wa(r, t, "top"),
		bottom: Wa(r, t, "bottom")
	} : t;
}
function Ka(e, t) {
	let n = t._clip;
	if (n.disabled) return !1;
	let r = Ga(t, e.chartArea);
	return {
		left: n.left === !1 ? 0 : r.left - (n.left === !0 ? 0 : n.left),
		right: n.right === !1 ? e.width : r.right + (n.right === !0 ? 0 : n.right),
		top: n.top === !1 ? 0 : r.top - (n.top === !0 ? 0 : n.top),
		bottom: n.bottom === !1 ? e.height : r.bottom + (n.bottom === !0 ? 0 : n.bottom)
	};
}
var qa = /* #__PURE__ */ new class {
	constructor() {
		this._request = null, this._charts = /* @__PURE__ */ new Map(), this._running = !1, this._lastDate = void 0;
	}
	_notify(e, t, n, r) {
		let i = t.listeners[r], a = t.duration;
		i.forEach((r) => r({
			chart: e,
			initial: t.initial,
			numSteps: a,
			currentStep: Math.min(n - t.start, a)
		}));
	}
	_refresh() {
		this._request ||= (this._running = !0, jr.call(window, () => {
			this._update(), this._request = null, this._running && this._refresh();
		}));
	}
	_update(e = Date.now()) {
		let t = 0;
		this._charts.forEach((n, r) => {
			if (!n.running || !n.items.length) return;
			let i = n.items, a = i.length - 1, o = !1, s;
			for (; a >= 0; --a) s = i[a], s._active ? (s._total > n.duration && (n.duration = s._total), s.tick(e), o = !0) : (i[a] = i[i.length - 1], i.pop());
			o && (r.draw(), this._notify(r, n, e, "progress")), i.length || (n.running = !1, this._notify(r, n, e, "complete"), n.initial = !1), t += i.length;
		}), this._lastDate = e, t === 0 && (this._running = !1);
	}
	_getAnims(e) {
		let t = this._charts, n = t.get(e);
		return n || (n = {
			running: !1,
			initial: !0,
			items: [],
			listeners: {
				complete: [],
				progress: []
			}
		}, t.set(e, n)), n;
	}
	listen(e, t, n) {
		this._getAnims(e).listeners[t].push(n);
	}
	add(e, t) {
		t && t.length && this._getAnims(e).items.push(...t);
	}
	has(e) {
		return this._getAnims(e).items.length > 0;
	}
	start(e) {
		let t = this._charts.get(e);
		t && (t.running = !0, t.start = Date.now(), t.duration = t.items.reduce((e, t) => Math.max(e, t._duration), 0), this._refresh());
	}
	running(e) {
		if (!this._running) return !1;
		let t = this._charts.get(e);
		return !(!t || !t.running || !t.items.length);
	}
	stop(e) {
		let t = this._charts.get(e);
		if (!t || !t.items.length) return;
		let n = t.items, r = n.length - 1;
		for (; r >= 0; --r) n[r].cancel();
		t.items = [], this._notify(e, t, Date.now(), "complete");
	}
	remove(e) {
		return this._charts.delete(e);
	}
}(), Ja = "transparent", Ya = {
	boolean(e, t, n) {
		return n > .5 ? t : e;
	},
	color(e, t, n) {
		let r = Ur(e || Ja), i = r.valid && Ur(t || Ja);
		return i && i.valid ? i.mix(r, n).hexString() : t;
	},
	number(e, t, n) {
		return e + (t - e) * n;
	}
}, Xa = class {
	constructor(e, t, n, r) {
		let i = t[n];
		r = Ai([
			e.to,
			r,
			i,
			e.from
		]);
		let a = Ai([
			e.from,
			i,
			r
		]);
		this._active = !0, this._fn = e.fn || Ya[e.type || typeof a], this._easing = Vr[e.easing] || Vr.linear, this._start = Math.floor(Date.now() + (e.delay || 0)), this._duration = this._total = Math.floor(e.duration), this._loop = !!e.loop, this._target = t, this._prop = n, this._from = a, this._to = r, this._promises = void 0;
	}
	active() {
		return this._active;
	}
	update(e, t, n) {
		if (this._active) {
			this._notify(!1);
			let r = this._target[this._prop], i = n - this._start, a = this._duration - i;
			this._start = n, this._duration = Math.floor(Math.max(a, e.duration)), this._total += i, this._loop = !!e.loop, this._to = Ai([
				e.to,
				t,
				r,
				e.from
			]), this._from = Ai([
				e.from,
				r,
				t
			]);
		}
	}
	cancel() {
		this._active && (this.tick(Date.now()), this._active = !1, this._notify(!1));
	}
	tick(e) {
		let t = e - this._start, n = this._duration, r = this._prop, i = this._from, a = this._loop, o = this._to, s;
		this._active = i !== o && (a || t < n), this._active ? t < 0 ? this._target[r] = i : (s = t / n % 2, s = a && s > 1 ? 2 - s : s, s = this._easing(Math.min(1, Math.max(0, s))), this._target[r] = this._fn(i, o, s)) : (this._target[r] = o, this._notify(!0));
	}
	wait() {
		let e = this._promises ||= [];
		return new Promise((t, n) => {
			e.push({
				res: t,
				rej: n
			});
		});
	}
	_notify(e) {
		let t = e ? "res" : "rej", n = this._promises || [];
		for (let e = 0; e < n.length; e++) n[e][t]();
	}
}, Za = class {
	constructor(e, t) {
		this._chart = e, this._properties = /* @__PURE__ */ new Map(), this.configure(t);
	}
	configure(e) {
		if (!I(e)) return;
		let t = Object.keys(q.animation), n = this._properties;
		Object.getOwnPropertyNames(e).forEach((r) => {
			let i = e[r];
			if (!I(i)) return;
			let a = {};
			for (let e of t) a[e] = i[e];
			(F(i.properties) && i.properties || [r]).forEach((e) => {
				e !== r && n.has(e) || n.set(e, a);
			});
		});
	}
	_animateOptions(e, t) {
		let n = t.options, r = $a(e, n);
		if (!r) return [];
		let i = this._createAnimations(r, n);
		return n.$shared && Qa(e.options.$animations, n).then(() => {
			e.options = n;
		}, () => {}), i;
	}
	_createAnimations(e, t) {
		let n = this._properties, r = [], i = e.$animations ||= {}, a = Object.keys(t), o = Date.now(), s;
		for (s = a.length - 1; s >= 0; --s) {
			let c = a[s];
			if (c.charAt(0) === "$") continue;
			if (c === "options") {
				r.push(...this._animateOptions(e, t));
				continue;
			}
			let l = t[c], u = i[c], d = n.get(c);
			if (u) {
				if (d && u.active()) {
					u.update(d, l, o);
					continue;
				}
				u.cancel();
			}
			!d || !d.duration ? e[c] = l : (i[c] = u = new Xa(d, e, c, l), r.push(u));
		}
		return r;
	}
	update(e, t) {
		if (this._properties.size === 0) {
			Object.assign(e, t);
			return;
		}
		let n = this._createAnimations(e, t);
		if (n.length) return qa.add(this._chart, n), !0;
	}
};
function Qa(e, t) {
	let n = [], r = Object.keys(t);
	for (let t = 0; t < r.length; t++) {
		let i = e[r[t]];
		i && i.active() && n.push(i.wait());
	}
	return Promise.all(n);
}
function $a(e, t) {
	if (!t) return;
	let n = e.options;
	if (!n) e.options = t;
	else return n.$shared && (e.options = n = Object.assign({}, n, {
		$shared: !1,
		$animations: {}
	})), n;
}
function eo(e, t) {
	let n = e && e.options || {}, r = n.reverse, i = n.min === void 0 ? t : 0, a = n.max === void 0 ? t : 0;
	return {
		start: r ? a : i,
		end: r ? i : a
	};
}
function to(e, t, n) {
	if (n === !1) return !1;
	let r = eo(e, n), i = eo(t, n);
	return {
		top: i.end,
		right: r.end,
		bottom: i.start,
		left: r.start
	};
}
function no(e) {
	let t, n, r, i;
	return I(e) ? (t = e.top, n = e.right, r = e.bottom, i = e.left) : t = n = r = i = e, {
		top: t,
		right: n,
		bottom: r,
		left: i,
		disabled: e === !1
	};
}
function ro(e, t) {
	let n = [], r = e._getSortedDatasetMetas(t), i, a;
	for (i = 0, a = r.length; i < a; ++i) n.push(r[i].index);
	return n;
}
function io(e, t, n, r = {}) {
	let i = e.keys, a = r.mode === "single", o, s, c, l;
	if (t === null) return;
	let u = !1;
	for (o = 0, s = i.length; o < s; ++o) {
		if (c = +i[o], c === n) {
			if (u = !0, r.all) continue;
			break;
		}
		l = e.values[c], L(l) && (a || t === 0 || or(t) === or(l)) && (t += l);
	}
	return !u && !r.all ? 0 : t;
}
function ao(e, t) {
	let { iScale: n, vScale: r } = t, i = n.axis === "x" ? "x" : "y", a = r.axis === "x" ? "x" : "y", o = Object.keys(e), s = Array(o.length), c, l, u;
	for (c = 0, l = o.length; c < l; ++c) u = o[c], s[c] = {
		[i]: u,
		[a]: e[u]
	};
	return s;
}
function oo(e, t) {
	let n = e && e.options.stacked;
	return n || n === void 0 && t.stack !== void 0;
}
function so(e, t, n) {
	return `${e.id}.${t.id}.${n.stack || n.type}`;
}
function co(e) {
	let { min: t, max: n, minDefined: r, maxDefined: i } = e.getUserBounds();
	return {
		min: r ? t : -Infinity,
		max: i ? n : Infinity
	};
}
function lo(e, t, n) {
	let r = e[t] || (e[t] = {});
	return r[n] || (r[n] = {});
}
function uo(e, t, n, r) {
	for (let i of t.getMatchingVisibleMetas(r).reverse()) {
		let t = e[i.index];
		if (n && t > 0 || !n && t < 0) return i.index;
	}
	return null;
}
function fo(e, t) {
	let { chart: n, _cachedMeta: r } = e, i = n._stacks ||= {}, { iScale: a, vScale: o, index: s } = r, c = a.axis, l = o.axis, u = so(a, o, r), d = t.length, f;
	for (let e = 0; e < d; ++e) {
		let n = t[e], { [c]: a, [l]: d } = n, p = n._stacks ||= {};
		f = p[l] = lo(i, u, a), f[s] = d, f._top = uo(f, o, !0, r.type), f._bottom = uo(f, o, !1, r.type);
		let m = f._visualValues || (f._visualValues = {});
		m[s] = d;
	}
}
function po(e, t) {
	let n = e.scales;
	return Object.keys(n).filter((e) => n[e].axis === t).shift();
}
function mo(e, t) {
	return Mi(e, {
		active: !1,
		dataset: void 0,
		datasetIndex: t,
		index: t,
		mode: "default",
		type: "dataset"
	});
}
function ho(e, t, n) {
	return Mi(e, {
		active: !1,
		dataIndex: t,
		parsed: void 0,
		raw: void 0,
		element: n,
		index: t,
		mode: "default",
		type: "data"
	});
}
function go(e, t) {
	let n = e.controller.index, r = e.vScale && e.vScale.axis;
	if (r) {
		t ||= e._parsed;
		for (let e of t) {
			let t = e._stacks;
			t && t[r] !== void 0 && t[r][n] !== void 0 && (delete t[r][n], t[r]._visualValues !== void 0 && t[r]._visualValues[n] !== void 0 && delete t[r]._visualValues[n]);
		}
	}
}
var _o = (e) => e === "reset" || e === "none", vo = (e, t) => t ? e : Object.assign({}, e), yo = (e, t, n) => e && !t.hidden && t._stacked && {
	keys: ro(n, !0),
	values: null
}, bo = class {
	static defaults = {};
	static datasetElementType = null;
	static dataElementType = null;
	constructor(e, t) {
		this.chart = e, this._ctx = e.ctx, this.index = t, this._cachedDataOpts = {}, this._cachedMeta = this.getMeta(), this._type = this._cachedMeta.type, this.options = void 0, this._parsing = !1, this._data = void 0, this._objectData = void 0, this._sharedOptions = void 0, this._drawStart = void 0, this._drawCount = void 0, this.enableOptionSharing = !1, this.supportsDecimation = !1, this.$context = void 0, this._syncList = [], this.datasetElementType = new.target.datasetElementType, this.dataElementType = new.target.dataElementType, this.initialize();
	}
	initialize() {
		let e = this._cachedMeta;
		this.configure(), this.linkScales(), e._stacked = oo(e.vScale, e), this.addElements(), this.options.fill && !this.chart.isPluginEnabled("filler") && console.warn("Tried to use the 'fill' option without the 'Filler' plugin enabled. Please import and register the 'Filler' plugin and make sure it is not disabled in the options");
	}
	updateIndex(e) {
		this.index !== e && go(this._cachedMeta), this.index = e;
	}
	linkScales() {
		let e = this.chart, t = this._cachedMeta, n = this.getDataset(), r = (e, t, n, r) => e === "x" ? t : e === "r" ? r : n, i = t.xAxisID = z(n.xAxisID, po(e, "x")), a = t.yAxisID = z(n.yAxisID, po(e, "y")), o = t.rAxisID = z(n.rAxisID, po(e, "r")), s = t.indexAxis, c = t.iAxisID = r(s, i, a, o), l = t.vAxisID = r(s, a, i, o);
		t.xScale = this.getScaleForId(i), t.yScale = this.getScaleForId(a), t.rScale = this.getScaleForId(o), t.iScale = this.getScaleForId(c), t.vScale = this.getScaleForId(l);
	}
	getDataset() {
		return this.chart.data.datasets[this.index];
	}
	getMeta() {
		return this.chart.getDatasetMeta(this.index);
	}
	getScaleForId(e) {
		return this.chart.scales[e];
	}
	_getOtherScale(e) {
		let t = this._cachedMeta;
		return e === t.iScale ? t.vScale : t.iScale;
	}
	reset() {
		this._update("reset");
	}
	_destroy() {
		let e = this._cachedMeta;
		this._data && kr(this._data, this), e._stacked && go(e);
	}
	_dataCheck() {
		let e = this.getDataset(), t = e.data ||= [], n = this._data;
		if (I(t)) {
			let e = this._cachedMeta;
			this._data = ao(t, e);
		} else if (n !== t) {
			if (n) {
				kr(n, this);
				let e = this._cachedMeta;
				go(e), e._parsed = [];
			}
			t && Object.isExtensible(t) && Or(t, this), this._syncList = [], this._data = t;
		}
	}
	addElements() {
		let e = this._cachedMeta;
		this._dataCheck(), this.datasetElementType && (e.dataset = new this.datasetElementType());
	}
	buildOrUpdateElements(e) {
		let t = this._cachedMeta, n = this.getDataset(), r = !1;
		this._dataCheck();
		let i = t._stacked;
		t._stacked = oo(t.vScale, t), t.stack !== n.stack && (r = !0, go(t), t.stack = n.stack), this._resyncElements(e), (r || i !== t._stacked) && (fo(this, t._parsed), t._stacked = oo(t.vScale, t));
	}
	configure() {
		let e = this.chart.config, t = e.datasetScopeKeys(this._type), n = e.getOptionScopes(this.getDataset(), t, !0);
		this.options = e.createResolver(n, this.getContext()), this._parsing = this.options.parsing, this._cachedDataOpts = {};
	}
	parse(e, t) {
		let { _cachedMeta: n, _data: r } = this, { iScale: i, _stacked: a } = n, o = i.axis, s = e === 0 && t === r.length || n._sorted, c = e > 0 && n._parsed[e - 1], l, u, d;
		if (this._parsing === !1) n._parsed = r, n._sorted = !0, d = r;
		else {
			d = F(r[e]) ? this.parseArrayData(n, r, e, t) : I(r[e]) ? this.parseObjectData(n, r, e, t) : this.parsePrimitiveData(n, r, e, t);
			let i = () => u[o] === null || c && u[o] < c[o];
			for (l = 0; l < t; ++l) n._parsed[l + e] = u = d[l], s && (i() && (s = !1), c = u);
			n._sorted = s;
		}
		a && fo(this, d);
	}
	parsePrimitiveData(e, t, n, r) {
		let { iScale: i, vScale: a } = e, o = i.axis, s = a.axis, c = i.getLabels(), l = i === a, u = Array(r), d, f, p;
		for (d = 0, f = r; d < f; ++d) p = d + n, u[d] = {
			[o]: l || i.parse(c[p], p),
			[s]: a.parse(t[p], p)
		};
		return u;
	}
	parseArrayData(e, t, n, r) {
		let { xScale: i, yScale: a } = e, o = Array(r), s, c, l, u;
		for (s = 0, c = r; s < c; ++s) l = s + n, u = t[l], o[s] = {
			x: i.parse(u[0], l),
			y: a.parse(u[1], l)
		};
		return o;
	}
	parseObjectData(e, t, n, r) {
		let { xScale: i, yScale: a } = e, { xAxisKey: o = "x", yAxisKey: s = "y" } = this._parsing, c = Array(r), l, u, d, f;
		for (l = 0, u = r; l < u; ++l) d = l + n, f = t[d], c[l] = {
			x: i.parse(Jn(f, o), d),
			y: a.parse(Jn(f, s), d)
		};
		return c;
	}
	getParsed(e) {
		return this._cachedMeta._parsed[e];
	}
	getDataElement(e) {
		return this._cachedMeta.data[e];
	}
	applyStack(e, t, n) {
		let r = this.chart, i = this._cachedMeta, a = t[e.axis];
		return io({
			keys: ro(r, !0),
			values: t._stacks[e.axis]._visualValues
		}, a, i.index, { mode: n });
	}
	updateRangeFromParsed(e, t, n, r) {
		let i = n[t.axis], a = i === null ? NaN : i, o = r && n._stacks[t.axis];
		r && o && (r.values = o, a = io(r, i, this._cachedMeta.index)), e.min = Math.min(e.min, a), e.max = Math.max(e.max, a);
	}
	getMinMax(e, t) {
		let n = this._cachedMeta, r = n._parsed, i = n._sorted && e === n.iScale, a = r.length, o = this._getOtherScale(e), s = yo(t, n, this.chart), c = {
			min: Infinity,
			max: -Infinity
		}, { min: l, max: u } = co(o), d, f;
		function p() {
			f = r[d];
			let t = f[o.axis];
			return !L(f[e.axis]) || l > t || u < t;
		}
		for (d = 0; d < a && !(!p() && (this.updateRangeFromParsed(c, e, f, s), i)); ++d);
		if (i) {
			for (d = a - 1; d >= 0; --d) if (!p()) {
				this.updateRangeFromParsed(c, e, f, s);
				break;
			}
		}
		return c;
	}
	getAllParsedValues(e) {
		let t = this._cachedMeta._parsed, n = [], r, i, a;
		for (r = 0, i = t.length; r < i; ++r) a = t[r][e.axis], L(a) && n.push(a);
		return n;
	}
	getMaxOverflow() {
		return !1;
	}
	getLabelAndValue(e) {
		let t = this._cachedMeta, n = t.iScale, r = t.vScale, i = this.getParsed(e);
		return {
			label: n ? "" + n.getLabelForValue(i[n.axis]) : "",
			value: r ? "" + r.getLabelForValue(i[r.axis]) : ""
		};
	}
	_update(e) {
		let t = this._cachedMeta;
		this.update(e || "default"), t._clip = no(z(this.options.clip, to(t.xScale, t.yScale, this.getMaxOverflow())));
	}
	update(e) {}
	draw() {
		let e = this._ctx, t = this.chart, n = this._cachedMeta, r = n.data || [], i = t.chartArea, a = [], o = this._drawStart || 0, s = this._drawCount || r.length - o, c = this.options.drawActiveElementsOnTop, l;
		for (n.dataset && n.dataset.draw(e, i, o, s), l = o; l < o + s; ++l) {
			let t = r[l];
			t.hidden || (t.active && c ? a.push(t) : t.draw(e, i));
		}
		for (l = 0; l < a.length; ++l) a[l].draw(e, i);
	}
	getStyle(e, t) {
		let n = t ? "active" : "default";
		return e === void 0 && this._cachedMeta.dataset ? this.resolveDatasetElementOptions(n) : this.resolveDataElementOptions(e || 0, n);
	}
	getContext(e, t, n) {
		let r = this.getDataset(), i;
		if (e >= 0 && e < this._cachedMeta.data.length) {
			let t = this._cachedMeta.data[e];
			i = t.$context ||= ho(this.getContext(), e, t), i.parsed = this.getParsed(e), i.raw = r.data[e], i.index = i.dataIndex = e;
		} else i = this.$context ||= mo(this.chart.getContext(), this.index), i.dataset = r, i.index = i.datasetIndex = this.index;
		return i.active = !!t, i.mode = n, i;
	}
	resolveDatasetElementOptions(e) {
		return this._resolveElementOptions(this.datasetElementType.id, e);
	}
	resolveDataElementOptions(e, t) {
		return this._resolveElementOptions(this.dataElementType.id, t, e);
	}
	_resolveElementOptions(e, t = "default", n) {
		let r = t === "active", i = this._cachedDataOpts, a = e + "-" + t, o = i[a], s = this.enableOptionSharing && Xn(n);
		if (o) return vo(o, s);
		let c = this.chart.config, l = c.datasetElementScopeKeys(this._type, e), u = r ? [
			`${e}Hover`,
			"hover",
			e,
			""
		] : [e, ""], d = c.getOptionScopes(this.getDataset(), l), f = Object.keys(q.elements[e]), p = c.resolveNamedOptions(d, f, () => this.getContext(n, r, t), u);
		return p.$shared && (p.$shared = s, i[a] = Object.freeze(vo(p, s))), p;
	}
	_resolveAnimations(e, t, n) {
		let r = this.chart, i = this._cachedDataOpts, a = `animation-${t}`, o = i[a];
		if (o) return o;
		let s;
		if (r.options.animation !== !1) {
			let r = this.chart.config, i = r.datasetAnimationScopeKeys(this._type, t), a = r.getOptionScopes(this.getDataset(), i);
			s = r.createResolver(a, this.getContext(e, n, t));
		}
		let c = new Za(r, s && s.animations);
		return s && s._cacheable && (i[a] = Object.freeze(c)), c;
	}
	getSharedOptions(e) {
		if (e.$shared) return this._sharedOptions ||= Object.assign({}, e);
	}
	includeOptions(e, t) {
		return !t || _o(e) || this.chart._animationsDisabled;
	}
	_getSharedOptions(e, t) {
		let n = this.resolveDataElementOptions(e, t), r = this._sharedOptions, i = this.getSharedOptions(n), a = this.includeOptions(t, i) || i !== r;
		return this.updateSharedOptions(i, t, n), {
			sharedOptions: i,
			includeOptions: a
		};
	}
	updateElement(e, t, n, r) {
		_o(r) ? Object.assign(e, n) : this._resolveAnimations(t, r).update(e, n);
	}
	updateSharedOptions(e, t, n) {
		e && !_o(t) && this._resolveAnimations(void 0, t).update(e, n);
	}
	_setStyle(e, t, n, r) {
		e.active = r;
		let i = this.getStyle(t, r);
		this._resolveAnimations(t, n, r).update(e, { options: !r && this.getSharedOptions(i) || i });
	}
	removeHoverStyle(e, t, n) {
		this._setStyle(e, n, "active", !1);
	}
	setHoverStyle(e, t, n) {
		this._setStyle(e, n, "active", !0);
	}
	_removeDatasetHoverStyle() {
		let e = this._cachedMeta.dataset;
		e && this._setStyle(e, void 0, "active", !1);
	}
	_setDatasetHoverStyle() {
		let e = this._cachedMeta.dataset;
		e && this._setStyle(e, void 0, "active", !0);
	}
	_resyncElements(e) {
		let t = this._data, n = this._cachedMeta.data;
		for (let [e, t, n] of this._syncList) this[e](t, n);
		this._syncList = [];
		let r = n.length, i = t.length, a = Math.min(i, r);
		a && this.parse(0, a), i > r ? this._insertElements(r, i - r, e) : i < r && this._removeElements(i, r - i);
	}
	_insertElements(e, t, n = !0) {
		let r = this._cachedMeta, i = r.data, a = e + t, o, s = (e) => {
			for (e.length += t, o = e.length - 1; o >= a; o--) e[o] = e[o - t];
		};
		for (s(i), o = e; o < a; ++o) i[o] = new this.dataElementType();
		this._parsing && s(r._parsed), this.parse(e, t), n && this.updateElements(i, e, t, "reset");
	}
	updateElements(e, t, n, r) {}
	_removeElements(e, t) {
		let n = this._cachedMeta;
		if (this._parsing) {
			let r = n._parsed.splice(e, t);
			n._stacked && go(n, r);
		}
		n.data.splice(e, t);
	}
	_sync(e) {
		if (this._parsing) this._syncList.push(e);
		else {
			let [t, n, r] = e;
			this[t](n, r);
		}
		this.chart._dataChanges.push([this.index, ...e]);
	}
	_onDataPush() {
		let e = arguments.length;
		this._sync([
			"_insertElements",
			this.getDataset().data.length - e,
			e
		]);
	}
	_onDataPop() {
		this._sync([
			"_removeElements",
			this._cachedMeta.data.length - 1,
			1
		]);
	}
	_onDataShift() {
		this._sync([
			"_removeElements",
			0,
			1
		]);
	}
	_onDataSplice(e, t) {
		t && this._sync([
			"_removeElements",
			e,
			t
		]);
		let n = arguments.length - 2;
		n && this._sync([
			"_insertElements",
			e,
			n
		]);
	}
	_onDataUnshift() {
		this._sync([
			"_insertElements",
			0,
			arguments.length
		]);
	}
}, xo = class extends bo {
	static id = "line";
	static defaults = {
		datasetElementType: "line",
		dataElementType: "point",
		showLine: !0,
		spanGaps: !1
	};
	static overrides = { scales: {
		_index_: { type: "category" },
		_value_: { type: "linear" }
	} };
	initialize() {
		this.enableOptionSharing = !0, this.supportsDecimation = !0, super.initialize();
	}
	update(e) {
		let t = this._cachedMeta, { dataset: n, data: r = [], _dataset: i } = t, a = this.chart._animationsDisabled, { start: o, count: s } = Ir(t, r, a);
		this._drawStart = o, this._drawCount = s, Lr(t) && (o = 0, s = r.length), n._chart = this.chart, n._datasetIndex = this.index, n._decimated = !!i._decimated, n.points = r;
		let c = this.resolveDatasetElementOptions(e);
		this.options.showLine || (c.borderWidth = 0), c.segment = this.options.segment, this.updateElement(n, void 0, {
			animated: !a,
			options: c
		}, e), this.updateElements(r, o, s, e);
	}
	updateElements(e, t, n, r) {
		let i = r === "reset", { iScale: a, vScale: o, _stacked: s, _dataset: c } = this._cachedMeta, { sharedOptions: l, includeOptions: u } = this._getSharedOptions(t, r), d = a.axis, f = o.axis, { spanGaps: p, segment: m } = this.options, h = dr(p) ? p : Infinity, g = this.chart._animationsDisabled || i || r === "none", _ = t + n, v = e.length, y = t > 0 && this.getParsed(t - 1);
		for (let n = 0; n < v; ++n) {
			let p = e[n], v = g ? p : {};
			if (n < t || n >= _) {
				v.skip = !0;
				continue;
			}
			let b = this.getParsed(n), x = P(b[f]), S = v[d] = a.getPixelForValue(b[d], n), C = v[f] = i || x ? o.getBasePixel() : o.getPixelForValue(s ? this.applyStack(o, b, s) : b[f], n);
			v.skip = isNaN(S) || isNaN(C) || x, v.stop = n > 0 && Math.abs(b[d] - y[d]) > h, m && (v.parsed = b, v.raw = c.data[n]), u && (v.options = l || this.resolveDataElementOptions(n, p.active ? "active" : r)), g || this.updateElement(p, n, v, r), y = b;
		}
	}
	getMaxOverflow() {
		let e = this._cachedMeta, t = e.dataset, n = t.options && t.options.borderWidth || 0, r = e.data || [];
		if (!r.length) return n;
		let i = r[0].size(this.resolveDataElementOptions(0)), a = r[r.length - 1].size(this.resolveDataElementOptions(r.length - 1));
		return Math.max(n, i, a) / 2;
	}
	draw() {
		let e = this._cachedMeta;
		e.dataset.updateControlPoints(this.chart.chartArea, e.iScale.axis), super.draw();
	}
};
function So() {
	throw Error("This method is not implemented: Check that a complete date adapter is provided.");
}
var Co = { _date: class e {
	static override(t) {
		Object.assign(e.prototype, t);
	}
	options;
	constructor(e) {
		this.options = e || {};
	}
	init() {}
	formats() {
		return So();
	}
	parse() {
		return So();
	}
	format() {
		return So();
	}
	add() {
		return So();
	}
	diff() {
		return So();
	}
	startOf() {
		return So();
	}
	endOf() {
		return So();
	}
} };
function wo(e, t, n, r) {
	let { controller: i, data: a, _sorted: o } = e, s = i._cachedMeta.iScale, c = e.dataset && e.dataset.options ? e.dataset.options.spanGaps : null;
	if (s && t === s.axis && t !== "r" && o && a.length) {
		let o = s._reversePixels ? Tr : wr;
		if (!r) {
			let r = o(a, t, n);
			if (c) {
				let { vScale: t } = i._cachedMeta, { _parsed: n } = e, a = n.slice(0, r.lo + 1).reverse().findIndex((e) => !P(e[t.axis]));
				r.lo -= Math.max(0, a);
				let o = n.slice(r.hi).findIndex((e) => !P(e[t.axis]));
				r.hi += Math.max(0, o);
			}
			return r;
		}
		if (i._sharedOptions) {
			let e = a[0], r = typeof e.getRange == "function" && e.getRange(t);
			if (r) {
				let e = o(a, t, n - r), i = o(a, t, n + r);
				return {
					lo: e.lo,
					hi: i.hi
				};
			}
		}
	}
	return {
		lo: 0,
		hi: a.length - 1
	};
}
function To(e, t, n, r, i) {
	let a = e.getSortedVisibleDatasetMetas(), o = n[t];
	for (let e = 0, n = a.length; e < n; ++e) {
		let { index: n, data: s } = a[e], { lo: c, hi: l } = wo(a[e], t, o, i);
		for (let e = c; e <= l; ++e) {
			let t = s[e];
			t.skip || r(t, n, e);
		}
	}
}
function Eo(e) {
	let t = e.indexOf("x") !== -1, n = e.indexOf("y") !== -1;
	return function(e, r) {
		let i = t ? Math.abs(e.x - r.x) : 0, a = n ? Math.abs(e.y - r.y) : 0;
		return Math.sqrt(i ** 2 + a ** 2);
	};
}
function Do(e, t, n, r, i) {
	let a = [];
	return !i && !e.isPointInArea(t) || To(e, n, t, function(n, o, s) {
		(i || pi(n, e.chartArea, 0)) && n.inRange(t.x, t.y, r) && a.push({
			element: n,
			datasetIndex: o,
			index: s
		});
	}, !0), a;
}
function Oo(e, t, n, r) {
	let i = [];
	function a(e, n, a) {
		let { startAngle: o, endAngle: s } = e.getProps(["startAngle", "endAngle"], r), { angle: c } = _r(e, {
			x: t.x,
			y: t.y
		});
		br(c, o, s) && i.push({
			element: e,
			datasetIndex: n,
			index: a
		});
	}
	return To(e, n, t, a), i;
}
function ko(e, t, n, r, i, a) {
	let o = [], s = Eo(n), c = Infinity;
	function l(n, l, u) {
		let d = n.inRange(t.x, t.y, i);
		if (r && !d) return;
		let f = n.getCenterPoint(i);
		if (!(a || e.isPointInArea(f)) && !d) return;
		let p = s(t, f);
		p < c ? (o = [{
			element: n,
			datasetIndex: l,
			index: u
		}], c = p) : p === c && o.push({
			element: n,
			datasetIndex: l,
			index: u
		});
	}
	return To(e, n, t, l), o;
}
function Ao(e, t, n, r, i, a) {
	return !a && !e.isPointInArea(t) ? [] : n === "r" && !r ? Oo(e, t, n, i) : ko(e, t, n, r, i, a);
}
function jo(e, t, n, r, i) {
	let a = [], o = n === "x" ? "inXRange" : "inYRange", s = !1;
	return To(e, n, t, (e, r, c) => {
		e[o] && e[o](t[n], i) && (a.push({
			element: e,
			datasetIndex: r,
			index: c
		}), s ||= e.inRange(t.x, t.y, i));
	}), r && !s ? [] : a;
}
var Mo = {
	evaluateInteractionItems: To,
	modes: {
		index(e, t, n, r) {
			let i = _a(t, e), a = n.axis || "x", o = n.includeInvisible || !1, s = n.intersect ? Do(e, i, a, r, o) : Ao(e, i, a, !1, r, o), c = [];
			return s.length ? (e.getSortedVisibleDatasetMetas().forEach((e) => {
				let t = s[0].index, n = e.data[t];
				n && !n.skip && c.push({
					element: n,
					datasetIndex: e.index,
					index: t
				});
			}), c) : [];
		},
		dataset(e, t, n, r) {
			let i = _a(t, e), a = n.axis || "xy", o = n.includeInvisible || !1, s = n.intersect ? Do(e, i, a, r, o) : Ao(e, i, a, !1, r, o);
			if (s.length > 0) {
				let t = s[0].datasetIndex, n = e.getDatasetMeta(t).data;
				s = [];
				for (let e = 0; e < n.length; ++e) s.push({
					element: n[e],
					datasetIndex: t,
					index: e
				});
			}
			return s;
		},
		point(e, t, n, r) {
			return Do(e, _a(t, e), n.axis || "xy", r, n.includeInvisible || !1);
		},
		nearest(e, t, n, r) {
			let i = _a(t, e), a = n.axis || "xy", o = n.includeInvisible || !1;
			return Ao(e, i, a, n.intersect, r, o);
		},
		x(e, t, n, r) {
			return jo(e, _a(t, e), "x", n.intersect, r);
		},
		y(e, t, n, r) {
			return jo(e, _a(t, e), "y", n.intersect, r);
		}
	}
}, No = [
	"left",
	"top",
	"right",
	"bottom"
];
function Po(e, t) {
	return e.filter((e) => e.pos === t);
}
function Fo(e, t) {
	return e.filter((e) => No.indexOf(e.pos) === -1 && e.box.axis === t);
}
function Io(e, t) {
	return e.sort((e, n) => {
		let r = t ? n : e, i = t ? e : n;
		return r.weight === i.weight ? r.index - i.index : r.weight - i.weight;
	});
}
function Lo(e) {
	let t = [], n, r, i, a, o, s;
	for (n = 0, r = (e || []).length; n < r; ++n) i = e[n], {position: a, options: {stack: o, stackWeight: s = 1}} = i, t.push({
		index: n,
		box: i,
		pos: a,
		horizontal: i.isHorizontal(),
		weight: i.weight,
		stack: o && a + o,
		stackWeight: s
	});
	return t;
}
function Ro(e) {
	let t = {};
	for (let n of e) {
		let { stack: e, pos: r, stackWeight: i } = n;
		if (!e || !No.includes(r)) continue;
		let a = t[e] || (t[e] = {
			count: 0,
			placed: 0,
			weight: 0,
			size: 0
		});
		a.count++, a.weight += i;
	}
	return t;
}
function zo(e, t) {
	let n = Ro(e), { vBoxMaxWidth: r, hBoxMaxHeight: i } = t, a, o, s;
	for (a = 0, o = e.length; a < o; ++a) {
		s = e[a];
		let { fullSize: o } = s.box, c = n[s.stack], l = c && s.stackWeight / c.weight;
		s.horizontal ? (s.width = l ? l * r : o && t.availableWidth, s.height = i) : (s.width = r, s.height = l ? l * i : o && t.availableHeight);
	}
	return n;
}
function Bo(e) {
	let t = Lo(e), n = Io(t.filter((e) => e.box.fullSize), !0), r = Io(Po(t, "left"), !0), i = Io(Po(t, "right")), a = Io(Po(t, "top"), !0), o = Io(Po(t, "bottom")), s = Fo(t, "x"), c = Fo(t, "y");
	return {
		fullSize: n,
		leftAndTop: r.concat(a),
		rightAndBottom: i.concat(c).concat(o).concat(s),
		chartArea: Po(t, "chartArea"),
		vertical: r.concat(i).concat(c),
		horizontal: a.concat(o).concat(s)
	};
}
function Vo(e, t, n, r) {
	return Math.max(e[n], t[n]) + Math.max(e[r], t[r]);
}
function Ho(e, t) {
	e.top = Math.max(e.top, t.top), e.left = Math.max(e.left, t.left), e.bottom = Math.max(e.bottom, t.bottom), e.right = Math.max(e.right, t.right);
}
function Uo(e, t, n, r) {
	let { pos: i, box: a } = n, o = e.maxPadding;
	if (!I(i)) {
		n.size && (e[i] -= n.size);
		let t = r[n.stack] || {
			size: 0,
			count: 1
		};
		t.size = Math.max(t.size, n.horizontal ? a.height : a.width), n.size = t.size / t.count, e[i] += n.size;
	}
	a.getPadding && Ho(o, a.getPadding());
	let s = Math.max(0, t.outerWidth - Vo(o, e, "left", "right")), c = Math.max(0, t.outerHeight - Vo(o, e, "top", "bottom")), l = s !== e.w, u = c !== e.h;
	return e.w = s, e.h = c, n.horizontal ? {
		same: l,
		other: u
	} : {
		same: u,
		other: l
	};
}
function Wo(e) {
	let t = e.maxPadding;
	function n(n) {
		let r = Math.max(t[n] - e[n], 0);
		return e[n] += r, r;
	}
	e.y += n("top"), e.x += n("left"), n("right"), n("bottom");
}
function Go(e, t) {
	let n = t.maxPadding;
	function r(e) {
		let r = {
			left: 0,
			top: 0,
			right: 0,
			bottom: 0
		};
		return e.forEach((e) => {
			r[e] = Math.max(t[e], n[e]);
		}), r;
	}
	return r(e ? ["left", "right"] : ["top", "bottom"]);
}
function Ko(e, t, n, r) {
	let i = [], a, o, s, c, l, u;
	for (a = 0, o = e.length, l = 0; a < o; ++a) {
		s = e[a], c = s.box, c.update(s.width || t.w, s.height || t.h, Go(s.horizontal, t));
		let { same: o, other: d } = Uo(t, n, s, r);
		l |= o && i.length, u ||= d, c.fullSize || i.push(s);
	}
	return l && Ko(i, t, n, r) || u;
}
function qo(e, t, n, r, i) {
	e.top = n, e.left = t, e.right = t + r, e.bottom = n + i, e.width = r, e.height = i;
}
function Jo(e, t, n, r) {
	let i = n.padding, { x: a, y: o } = t;
	for (let s of e) {
		let e = s.box, c = r[s.stack] || {
			count: 1,
			placed: 0,
			weight: 1
		}, l = s.stackWeight / c.weight || 1;
		if (s.horizontal) {
			let r = t.w * l, a = c.size || e.height;
			Xn(c.start) && (o = c.start), e.fullSize ? qo(e, i.left, o, n.outerWidth - i.right - i.left, a) : qo(e, t.left + c.placed, o, r, a), c.start = o, c.placed += r, o = e.bottom;
		} else {
			let r = t.h * l, o = c.size || e.width;
			Xn(c.start) && (a = c.start), e.fullSize ? qo(e, a, i.top, o, n.outerHeight - i.bottom - i.top) : qo(e, a, t.top + c.placed, o, r), c.start = a, c.placed += r, a = e.right;
		}
	}
	t.x = a, t.y = o;
}
var Yo = {
	addBox(e, t) {
		e.boxes ||= [], t.fullSize = t.fullSize || !1, t.position = t.position || "top", t.weight = t.weight || 0, t._layers = t._layers || function() {
			return [{
				z: 0,
				draw(e) {
					t.draw(e);
				}
			}];
		}, e.boxes.push(t);
	},
	removeBox(e, t) {
		let n = e.boxes ? e.boxes.indexOf(t) : -1;
		n !== -1 && e.boxes.splice(n, 1);
	},
	configure(e, t, n) {
		t.fullSize = n.fullSize, t.position = n.position, t.weight = n.weight;
	},
	update(e, t, n, r) {
		if (!e) return;
		let i = J(e.options.layout.padding), a = Math.max(t - i.width, 0), o = Math.max(n - i.height, 0), s = Bo(e.boxes), c = s.vertical, l = s.horizontal;
		V(e.boxes, (e) => {
			typeof e.beforeLayout == "function" && e.beforeLayout();
		});
		let u = c.reduce((e, t) => t.box.options && t.box.options.display === !1 ? e : e + 1, 0) || 1, d = Object.freeze({
			outerWidth: t,
			outerHeight: n,
			padding: i,
			availableWidth: a,
			availableHeight: o,
			vBoxMaxWidth: a / 2 / u,
			hBoxMaxHeight: o / 2
		}), f = Object.assign({}, i);
		Ho(f, J(r));
		let p = Object.assign({
			maxPadding: f,
			w: a,
			h: o,
			x: i.left,
			y: i.top
		}, i), m = zo(c.concat(l), d);
		Ko(s.fullSize, p, d, m), Ko(c, p, d, m), Ko(l, p, d, m) && Ko(c, p, d, m), Wo(p), Jo(s.leftAndTop, p, d, m), p.x += p.w, p.y += p.h, Jo(s.rightAndBottom, p, d, m), e.chartArea = {
			left: p.left,
			top: p.top,
			right: p.left + p.w,
			bottom: p.top + p.h,
			height: p.h,
			width: p.w
		}, V(s.chartArea, (t) => {
			let n = t.box;
			Object.assign(n, e.chartArea), n.update(p.w, p.h, {
				left: 0,
				top: 0,
				right: 0,
				bottom: 0
			});
		});
	}
}, Xo = class {
	acquireContext(e, t) {}
	releaseContext(e) {
		return !1;
	}
	addEventListener(e, t, n) {}
	removeEventListener(e, t, n) {}
	getDevicePixelRatio() {
		return 1;
	}
	getMaximumSize(e, t, n, r) {
		return t = Math.max(0, t || e.width), n ||= e.height, {
			width: t,
			height: Math.max(0, r ? Math.floor(t / r) : n)
		};
	}
	isAttached(e) {
		return !0;
	}
	updateConfig(e) {}
}, Zo = class extends Xo {
	acquireContext(e) {
		return e && e.getContext && e.getContext("2d") || null;
	}
	updateConfig(e) {
		e.options.animation = !1;
	}
}, Qo = "$chartjs", $o = {
	touchstart: "mousedown",
	touchmove: "mousemove",
	touchend: "mouseup",
	pointerenter: "mouseenter",
	pointerdown: "mousedown",
	pointermove: "mousemove",
	pointerup: "mouseup",
	pointerleave: "mouseout",
	pointerout: "mouseout"
}, es = (e) => e === null || e === "";
function ts(e, t) {
	let n = e.style, r = e.getAttribute("height"), i = e.getAttribute("width");
	if (e[Qo] = { initial: {
		height: r,
		width: i,
		style: {
			display: n.display,
			height: n.height,
			width: n.width
		}
	} }, n.display = n.display || "block", n.boxSizing = n.boxSizing || "border-box", es(i)) {
		let t = Ca(e, "width");
		t !== void 0 && (e.width = t);
	}
	if (es(r)) {
		if (e.style.height === "") e.height = e.width / (t || 2);
		else {
			let t = Ca(e, "height");
			t !== void 0 && (e.height = t);
		}
	}
	return e;
}
var ns = Sa ? { passive: !0 } : !1;
function rs(e, t, n) {
	e && e.addEventListener(t, n, ns);
}
function is(e, t, n) {
	e && e.canvas && e.canvas.removeEventListener(t, n, ns);
}
function as(e, t) {
	let n = $o[e.type] || e.type, { x: r, y: i } = _a(e, t);
	return {
		type: n,
		chart: t,
		native: e,
		x: r === void 0 ? null : r,
		y: i === void 0 ? null : i
	};
}
function os(e, t) {
	for (let n of e) if (n === t || n.contains(t)) return !0;
}
function ss(e, t, n) {
	let r = e.canvas, i = new MutationObserver((e) => {
		let t = !1;
		for (let n of e) t ||= os(n.addedNodes, r), t &&= !os(n.removedNodes, r);
		t && n();
	});
	return i.observe(document, {
		childList: !0,
		subtree: !0
	}), i;
}
function cs(e, t, n) {
	let r = e.canvas, i = new MutationObserver((e) => {
		let t = !1;
		for (let n of e) t ||= os(n.removedNodes, r), t &&= !os(n.addedNodes, r);
		t && n();
	});
	return i.observe(document, {
		childList: !0,
		subtree: !0
	}), i;
}
var ls = /* @__PURE__ */ new Map(), us = 0;
function ds() {
	let e = window.devicePixelRatio;
	e !== us && (us = e, ls.forEach((t, n) => {
		n.currentDevicePixelRatio !== e && t();
	}));
}
function fs(e, t) {
	ls.size || window.addEventListener("resize", ds), ls.set(e, t);
}
function ps(e) {
	ls.delete(e), ls.size || window.removeEventListener("resize", ds);
}
function ms(e, t, n) {
	let r = e.canvas, i = r && la(r);
	if (!i) return;
	let a = Mr((e, t) => {
		let r = i.clientWidth;
		n(e, t), r < i.clientWidth && n();
	}, window), o = new ResizeObserver((e) => {
		let t = e[0], n = t.contentRect.width, r = t.contentRect.height;
		(n !== 0 || r !== 0) && a(n, r);
	});
	return o.observe(i), fs(e, a), o;
}
function hs(e, t, n) {
	n && n.disconnect(), t === "resize" && ps(e);
}
function gs(e, t, n) {
	let r = e.canvas, i = Mr((t) => {
		e.ctx !== null && n(as(t, e));
	}, e);
	return rs(r, t, i), i;
}
var _s = class extends Xo {
	acquireContext(e, t) {
		let n = e && e.getContext && e.getContext("2d");
		return n && n.canvas === e ? (ts(e, t), n) : null;
	}
	releaseContext(e) {
		let t = e.canvas;
		if (!t[Qo]) return !1;
		let n = t[Qo].initial;
		["height", "width"].forEach((e) => {
			let r = n[e];
			P(r) ? t.removeAttribute(e) : t.setAttribute(e, r);
		});
		let r = n.style || {};
		return Object.keys(r).forEach((e) => {
			t.style[e] = r[e];
		}), t.width = t.width, delete t[Qo], !0;
	}
	addEventListener(e, t, n) {
		this.removeEventListener(e, t);
		let r = e.$proxies ||= {};
		r[t] = ({
			attach: ss,
			detach: cs,
			resize: ms
		}[t] || gs)(e, t, n);
	}
	removeEventListener(e, t) {
		let n = e.$proxies ||= {}, r = n[t];
		r && (({
			attach: hs,
			detach: hs,
			resize: hs
		}[t] || is)(e, t, r), n[t] = void 0);
	}
	getDevicePixelRatio() {
		return window.devicePixelRatio;
	}
	getMaximumSize(e, t, n, r) {
		return ba(e, t, n, r);
	}
	isAttached(e) {
		let t = e && la(e);
		return !!(t && t.isConnected);
	}
};
function vs(e) {
	return !ca() || typeof OffscreenCanvas < "u" && e instanceof OffscreenCanvas ? Zo : _s;
}
var ys = class {
	static defaults = {};
	static defaultRoutes = void 0;
	x;
	y;
	active = !1;
	options;
	$animations;
	tooltipPosition(e) {
		let { x: t, y: n } = this.getProps(["x", "y"], e);
		return {
			x: t,
			y: n
		};
	}
	hasValue() {
		return dr(this.x) && dr(this.y);
	}
	getProps(e, t) {
		let n = this.$animations;
		if (!t || !n) return this;
		let r = {};
		return e.forEach((e) => {
			r[e] = n[e] && n[e].active() ? n[e]._to : this[e];
		}), r;
	}
};
function bs(e, t) {
	let n = e.options.ticks, r = xs(e), i = Math.min(n.maxTicksLimit || r, r), a = n.major.enabled ? Cs(t) : [], o = a.length, s = a[0], c = a[o - 1], l = [];
	if (o > i) return ws(t, l, a, o / i), l;
	let u = Ss(a, t, i);
	if (o > 0) {
		let e, n, r = o > 1 ? Math.round((c - s) / (o - 1)) : null;
		for (Ts(t, l, u, P(r) ? 0 : s - r, s), e = 0, n = o - 1; e < n; e++) Ts(t, l, u, a[e], a[e + 1]);
		return Ts(t, l, u, c, P(r) ? t.length : c + r), l;
	}
	return Ts(t, l, u), l;
}
function xs(e) {
	let t = e.options.offset, n = e._tickSize(), r = e._length / n + +!t, i = e._maxLength / n;
	return Math.floor(Math.min(r, i));
}
function Ss(e, t, n) {
	let r = Es(e), i = t.length / n;
	if (!r) return Math.max(i, 1);
	let a = lr(r);
	for (let e = 0, t = a.length - 1; e < t; e++) {
		let t = a[e];
		if (t > i) return t;
	}
	return Math.max(i, 1);
}
function Cs(e) {
	let t = [], n, r;
	for (n = 0, r = e.length; n < r; n++) e[n].major && t.push(n);
	return t;
}
function ws(e, t, n, r) {
	let i = 0, a = n[0], o;
	for (r = Math.ceil(r), o = 0; o < e.length; o++) o === a && (t.push(e[o]), i++, a = n[i * r]);
}
function Ts(e, t, n, r, i) {
	let a = z(r, 0), o = Math.min(z(i, e.length), e.length), s = 0, c, l, u;
	for (n = Math.ceil(n), i && (c = i - r, n = c / Math.floor(c / n)), u = a; u < 0;) s++, u = Math.round(a + s * n);
	for (l = Math.max(a, 0); l < o; l++) l === u && (t.push(e[l]), s++, u = Math.round(a + s * n));
}
function Es(e) {
	let t = e.length, n, r;
	if (t < 2) return !1;
	for (r = e[0], n = 1; n < t; ++n) if (e[n] - e[n - 1] !== r) return !1;
	return r;
}
var Ds = (e) => e === "left" ? "right" : e === "right" ? "left" : e, Os = (e, t, n) => t === "top" || t === "left" ? e[t] + n : e[t] - n, ks = (e, t) => Math.min(t || e, e);
function As(e, t) {
	let n = [], r = e.length / t, i = e.length, a = 0;
	for (; a < i; a += r) n.push(e[Math.floor(a)]);
	return n;
}
function js(e, t, n) {
	let r = e.ticks.length, i = Math.min(t, r - 1), a = e._startPixel, o = e._endPixel, s = 1e-6, c = e.getPixelForTick(i), l;
	if (!(n && (l = r === 1 ? Math.max(c - a, o - c) : t === 0 ? (e.getPixelForTick(1) - c) / 2 : (c - e.getPixelForTick(i - 1)) / 2, c += i < t ? l : -l, c < a - s || c > o + s))) return c;
}
function Ms(e, t) {
	V(e, (e) => {
		let n = e.gc, r = n.length / 2, i;
		if (r > t) {
			for (i = 0; i < r; ++i) delete e.data[n[i]];
			n.splice(0, r);
		}
	});
}
function Ns(e) {
	return e.drawTicks ? e.tickLength : 0;
}
function Ps(e, t) {
	if (!e.display) return 0;
	let n = Y(e.font, t), r = J(e.padding);
	return (F(e.text) ? e.text.length : 1) * n.lineHeight + r.height;
}
function Fs(e, t) {
	return Mi(e, {
		scale: t,
		type: "scale"
	});
}
function Is(e, t, n) {
	return Mi(e, {
		tick: n,
		index: t,
		type: "tick"
	});
}
function Ls(e, t, n) {
	let r = Pr(e);
	return (n && t !== "right" || !n && t === "right") && (r = Ds(r)), r;
}
function Rs(e, t, n, r) {
	let { top: i, left: a, bottom: o, right: s, chart: c } = e, { chartArea: l, scales: u } = c, d = 0, f, p, m, h = o - i, g = s - a;
	if (e.isHorizontal()) {
		if (p = Fr(r, a, s), I(n)) {
			let e = Object.keys(n)[0], r = n[e];
			m = u[e].getPixelForValue(r) + h - t;
		} else m = n === "center" ? (l.bottom + l.top) / 2 + h - t : Os(e, n, t);
		f = s - a;
	} else {
		if (I(n)) {
			let e = Object.keys(n)[0], r = n[e];
			p = u[e].getPixelForValue(r) - g + t;
		} else p = n === "center" ? (l.left + l.right) / 2 - g + t : Os(e, n, t);
		m = Fr(r, o, i), d = n === "left" ? -W : W;
	}
	return {
		titleX: p,
		titleY: m,
		maxWidth: f,
		rotation: d
	};
}
var zs = class e extends ys {
	constructor(e) {
		super(), this.id = e.id, this.type = e.type, this.options = void 0, this.ctx = e.ctx, this.chart = e.chart, this.top = void 0, this.bottom = void 0, this.left = void 0, this.right = void 0, this.width = void 0, this.height = void 0, this._margins = {
			left: 0,
			right: 0,
			top: 0,
			bottom: 0
		}, this.maxWidth = void 0, this.maxHeight = void 0, this.paddingTop = void 0, this.paddingBottom = void 0, this.paddingLeft = void 0, this.paddingRight = void 0, this.axis = void 0, this.labelRotation = void 0, this.min = void 0, this.max = void 0, this._range = void 0, this.ticks = [], this._gridLineItems = null, this._labelItems = null, this._labelSizes = null, this._length = 0, this._maxLength = 0, this._longestTextCache = {}, this._startPixel = void 0, this._endPixel = void 0, this._reversePixels = !1, this._userMax = void 0, this._userMin = void 0, this._suggestedMax = void 0, this._suggestedMin = void 0, this._ticksLength = 0, this._borderValue = 0, this._cache = {}, this._dataLimitsCached = !1, this.$context = void 0;
	}
	init(e) {
		this.options = e.setContext(this.getContext()), this.axis = e.axis, this._userMin = this.parse(e.min), this._userMax = this.parse(e.max), this._suggestedMin = this.parse(e.suggestedMin), this._suggestedMax = this.parse(e.suggestedMax);
	}
	parse(e, t) {
		return e;
	}
	getUserBounds() {
		let { _userMin: e, _userMax: t, _suggestedMin: n, _suggestedMax: r } = this;
		return e = R(e, Infinity), t = R(t, -Infinity), n = R(n, Infinity), r = R(r, -Infinity), {
			min: R(e, n),
			max: R(t, r),
			minDefined: L(e),
			maxDefined: L(t)
		};
	}
	getMinMax(e) {
		let { min: t, max: n, minDefined: r, maxDefined: i } = this.getUserBounds(), a;
		if (r && i) return {
			min: t,
			max: n
		};
		let o = this.getMatchingVisibleMetas();
		for (let s = 0, c = o.length; s < c; ++s) a = o[s].controller.getMinMax(this, e), r || (t = Math.min(t, a.min)), i || (n = Math.max(n, a.max));
		return t = i && t > n ? n : t, n = r && t > n ? t : n, {
			min: R(t, R(n, t)),
			max: R(n, R(t, n))
		};
	}
	getPadding() {
		return {
			left: this.paddingLeft || 0,
			top: this.paddingTop || 0,
			right: this.paddingRight || 0,
			bottom: this.paddingBottom || 0
		};
	}
	getTicks() {
		return this.ticks;
	}
	getLabels() {
		let e = this.chart.data;
		return this.options.labels || (this.isHorizontal() ? e.xLabels : e.yLabels) || e.labels || [];
	}
	getLabelItems(e = this.chart.chartArea) {
		return this._labelItems ||= this._computeLabelItems(e);
	}
	beforeLayout() {
		this._cache = {}, this._dataLimitsCached = !1;
	}
	beforeUpdate() {
		B(this.options.beforeUpdate, [this]);
	}
	update(e, t, n) {
		let { beginAtZero: r, grace: i, ticks: a } = this.options, o = a.sampleSize;
		this.beforeUpdate(), this.maxWidth = e, this.maxHeight = t, this._margins = n = Object.assign({
			left: 0,
			right: 0,
			top: 0,
			bottom: 0
		}, n), this.ticks = null, this._labelSizes = null, this._gridLineItems = null, this._labelItems = null, this.beforeSetDimensions(), this.setDimensions(), this.afterSetDimensions(), this._maxLength = this.isHorizontal() ? this.width + n.left + n.right : this.height + n.top + n.bottom, this._dataLimitsCached ||= (this.beforeDataLimits(), this.determineDataLimits(), this.afterDataLimits(), this._range = ji(this, i, r), !0), this.beforeBuildTicks(), this.ticks = this.buildTicks() || [], this.afterBuildTicks();
		let s = o < this.ticks.length;
		this._convertTicksToLabels(s ? As(this.ticks, o) : this.ticks), this.configure(), this.beforeCalculateLabelRotation(), this.calculateLabelRotation(), this.afterCalculateLabelRotation(), a.display && (a.autoSkip || a.source === "auto") && (this.ticks = bs(this, this.ticks), this._labelSizes = null, this.afterAutoSkip()), s && this._convertTicksToLabels(this.ticks), this.beforeFit(), this.fit(), this.afterFit(), this.afterUpdate();
	}
	configure() {
		let e = this.options.reverse, t, n;
		this.isHorizontal() ? (t = this.left, n = this.right) : (t = this.top, n = this.bottom, e = !e), this._startPixel = t, this._endPixel = n, this._reversePixels = e, this._length = n - t, this._alignToPixels = this.options.alignToPixels;
	}
	afterUpdate() {
		B(this.options.afterUpdate, [this]);
	}
	beforeSetDimensions() {
		B(this.options.beforeSetDimensions, [this]);
	}
	setDimensions() {
		this.isHorizontal() ? (this.width = this.maxWidth, this.left = 0, this.right = this.width) : (this.height = this.maxHeight, this.top = 0, this.bottom = this.height), this.paddingLeft = 0, this.paddingTop = 0, this.paddingRight = 0, this.paddingBottom = 0;
	}
	afterSetDimensions() {
		B(this.options.afterSetDimensions, [this]);
	}
	_callHooks(e) {
		this.chart.notifyPlugins(e, this.getContext()), B(this.options[e], [this]);
	}
	beforeDataLimits() {
		this._callHooks("beforeDataLimits");
	}
	determineDataLimits() {}
	afterDataLimits() {
		this._callHooks("afterDataLimits");
	}
	beforeBuildTicks() {
		this._callHooks("beforeBuildTicks");
	}
	buildTicks() {
		return [];
	}
	afterBuildTicks() {
		this._callHooks("afterBuildTicks");
	}
	beforeTickToLabelConversion() {
		B(this.options.beforeTickToLabelConversion, [this]);
	}
	generateTickLabels(e) {
		let t = this.options.ticks, n, r, i;
		for (n = 0, r = e.length; n < r; n++) i = e[n], i.label = B(t.callback, [
			i.value,
			n,
			e
		], this);
	}
	afterTickToLabelConversion() {
		B(this.options.afterTickToLabelConversion, [this]);
	}
	beforeCalculateLabelRotation() {
		B(this.options.beforeCalculateLabelRotation, [this]);
	}
	calculateLabelRotation() {
		let e = this.options, t = e.ticks, n = ks(this.ticks.length, e.ticks.maxTicksLimit), r = t.minRotation || 0, i = t.maxRotation, a = r, o, s, c;
		if (!this._isVisible() || !t.display || r >= i || n <= 1 || !this.isHorizontal()) {
			this.labelRotation = r;
			return;
		}
		let l = this._getLabelSizes(), u = l.widest.width, d = l.highest.height, f = K(this.chart.width - u, 0, this.maxWidth);
		o = e.offset ? this.maxWidth / n : f / (n - 1), u + 6 > o && (o = f / (n - (e.offset ? .5 : 1)), s = this.maxHeight - Ns(e.grid) - t.padding - Ps(e.title, this.chart.options.font), c = Math.sqrt(u * u + d * d), a = hr(Math.min(Math.asin(K((l.highest.height + 6) / o, -1, 1)), Math.asin(K(s / c, -1, 1)) - Math.asin(K(d / c, -1, 1)))), a = Math.max(r, Math.min(i, a))), this.labelRotation = a;
	}
	afterCalculateLabelRotation() {
		B(this.options.afterCalculateLabelRotation, [this]);
	}
	afterAutoSkip() {}
	beforeFit() {
		B(this.options.beforeFit, [this]);
	}
	fit() {
		let e = {
			width: 0,
			height: 0
		}, { chart: t, options: { ticks: n, title: r, grid: i } } = this, a = this._isVisible(), o = this.isHorizontal();
		if (a) {
			let a = Ps(r, t.options.font);
			if (o ? (e.width = this.maxWidth, e.height = Ns(i) + a) : (e.height = this.maxHeight, e.width = Ns(i) + a), n.display && this.ticks.length) {
				let { first: t, last: r, widest: i, highest: a } = this._getLabelSizes(), s = n.padding * 2, c = mr(this.labelRotation), l = Math.cos(c), u = Math.sin(c);
				if (o) {
					let t = n.mirror ? 0 : u * i.width + l * a.height;
					e.height = Math.min(this.maxHeight, e.height + t + s);
				} else {
					let t = n.mirror ? 0 : l * i.width + u * a.height;
					e.width = Math.min(this.maxWidth, e.width + t + s);
				}
				this._calculatePadding(t, r, u, l);
			}
		}
		this._handleMargins(), o ? (this.width = this._length = t.width - this._margins.left - this._margins.right, this.height = e.height) : (this.width = e.width, this.height = this._length = t.height - this._margins.top - this._margins.bottom);
	}
	_calculatePadding(e, t, n, r) {
		let { ticks: { align: i, padding: a }, position: o } = this.options, s = this.labelRotation !== 0, c = o !== "top" && this.axis === "x";
		if (this.isHorizontal()) {
			let o = this.getPixelForTick(0) - this.left, l = this.right - this.getPixelForTick(this.ticks.length - 1), u = 0, d = 0;
			s ? c ? (u = r * e.width, d = n * t.height) : (u = n * e.height, d = r * t.width) : i === "start" ? d = t.width : i === "end" ? u = e.width : i !== "inner" && (u = e.width / 2, d = t.width / 2), this.paddingLeft = Math.max((u - o + a) * this.width / (this.width - o), 0), this.paddingRight = Math.max((d - l + a) * this.width / (this.width - l), 0);
		} else {
			let n = t.height / 2, r = e.height / 2;
			i === "start" ? (n = 0, r = e.height) : i === "end" && (n = t.height, r = 0), this.paddingTop = n + a, this.paddingBottom = r + a;
		}
	}
	_handleMargins() {
		this._margins && (this._margins.left = Math.max(this.paddingLeft, this._margins.left), this._margins.top = Math.max(this.paddingTop, this._margins.top), this._margins.right = Math.max(this.paddingRight, this._margins.right), this._margins.bottom = Math.max(this.paddingBottom, this._margins.bottom));
	}
	afterFit() {
		B(this.options.afterFit, [this]);
	}
	isHorizontal() {
		let { axis: e, position: t } = this.options;
		return t === "top" || t === "bottom" || e === "x";
	}
	isFullSize() {
		return this.options.fullSize;
	}
	_convertTicksToLabels(e) {
		this.beforeTickToLabelConversion(), this.generateTickLabels(e);
		let t, n;
		for (t = 0, n = e.length; t < n; t++) P(e[t].label) && (e.splice(t, 1), n--, t--);
		this.afterTickToLabelConversion();
	}
	_getLabelSizes() {
		let e = this._labelSizes;
		if (!e) {
			let t = this.options.ticks.sampleSize, n = this.ticks;
			t < n.length && (n = As(n, t)), this._labelSizes = e = this._computeLabelSizes(n, n.length, this.options.ticks.maxTicksLimit);
		}
		return e;
	}
	_computeLabelSizes(e, t, n) {
		let { ctx: r, _longestTextCache: i } = this, a = [], o = [], s = Math.floor(t / ks(t, n)), c = 0, l = 0, u, d, f, p, m, h, g, _, v, y, b;
		for (u = 0; u < t; u += s) {
			if (p = e[u].label, m = this._resolveTickFontOptions(u), r.font = h = m.string, g = i[h] = i[h] || {
				data: {},
				gc: []
			}, _ = m.lineHeight, v = y = 0, !P(p) && !F(p)) v = si(r, g.data, g.gc, v, p), y = _;
			else if (F(p)) for (d = 0, f = p.length; d < f; ++d) b = p[d], !P(b) && !F(b) && (v = si(r, g.data, g.gc, v, b), y += _);
			a.push(v), o.push(y), c = Math.max(v, c), l = Math.max(y, l);
		}
		Ms(i, t);
		let x = a.indexOf(c), S = o.indexOf(l), C = (e) => ({
			width: a[e] || 0,
			height: o[e] || 0
		});
		return {
			first: C(0),
			last: C(t - 1),
			widest: C(x),
			highest: C(S),
			widths: a,
			heights: o
		};
	}
	getLabelForValue(e) {
		return e;
	}
	getPixelForValue(e, t) {
		return NaN;
	}
	getValueForPixel(e) {}
	getPixelForTick(e) {
		let t = this.ticks;
		return e < 0 || e > t.length - 1 ? null : this.getPixelForValue(t[e].value);
	}
	getPixelForDecimal(e) {
		this._reversePixels && (e = 1 - e);
		let t = this._startPixel + e * this._length;
		return xr(this._alignToPixels ? li(this.chart, t, 0) : t);
	}
	getDecimalForPixel(e) {
		let t = (e - this._startPixel) / this._length;
		return this._reversePixels ? 1 - t : t;
	}
	getBasePixel() {
		return this.getPixelForValue(this.getBaseValue());
	}
	getBaseValue() {
		let { min: e, max: t } = this;
		return e < 0 && t < 0 ? t : e > 0 && t > 0 ? e : 0;
	}
	getContext(e) {
		let t = this.ticks || [];
		if (e >= 0 && e < t.length) {
			let n = t[e];
			return n.$context ||= Is(this.getContext(), e, n);
		}
		return this.$context ||= Fs(this.chart.getContext(), this);
	}
	_tickSize() {
		let e = this.options.ticks, t = mr(this.labelRotation), n = Math.abs(Math.cos(t)), r = Math.abs(Math.sin(t)), i = this._getLabelSizes(), a = e.autoSkipPadding || 0, o = i ? i.widest.width + a : 0, s = i ? i.highest.height + a : 0;
		return this.isHorizontal() ? s * n > o * r ? o / n : s / r : s * r < o * n ? s / n : o / r;
	}
	_isVisible() {
		let e = this.options.display;
		return e === "auto" ? this.getMatchingVisibleMetas().length > 0 : !!e;
	}
	_computeGridLineItems(e) {
		let t = this.axis, n = this.chart, r = this.options, { grid: i, position: a, border: o } = r, s = i.offset, c = this.isHorizontal(), l = this.ticks.length + +!!s, u = Ns(i), d = [], f = o.setContext(this.getContext()), p = f.display ? f.width : 0, m = p / 2, h = function(e) {
			return li(n, e, p);
		}, g, _, v, y, b, x, S, C, w, T, E, D;
		if (a === "top") g = h(this.bottom), x = this.bottom - u, C = g - m, T = h(e.top) + m, D = e.bottom;
		else if (a === "bottom") g = h(this.top), T = e.top, D = h(e.bottom) - m, x = g + m, C = this.top + u;
		else if (a === "left") g = h(this.right), b = this.right - u, S = g - m, w = h(e.left) + m, E = e.right;
		else if (a === "right") g = h(this.left), w = e.left, E = h(e.right) - m, b = g + m, S = this.left + u;
		else if (t === "x") {
			if (a === "center") g = h((e.top + e.bottom) / 2 + .5);
			else if (I(a)) {
				let e = Object.keys(a)[0], t = a[e];
				g = h(this.chart.scales[e].getPixelForValue(t));
			}
			T = e.top, D = e.bottom, x = g + m, C = x + u;
		} else if (t === "y") {
			if (a === "center") g = h((e.left + e.right) / 2);
			else if (I(a)) {
				let e = Object.keys(a)[0], t = a[e];
				g = h(this.chart.scales[e].getPixelForValue(t));
			}
			b = g - m, S = b - u, w = e.left, E = e.right;
		}
		let ee = z(r.ticks.maxTicksLimit, l), O = Math.max(1, Math.ceil(l / ee));
		for (_ = 0; _ < l; _ += O) {
			let e = this.getContext(_), t = i.setContext(e), r = o.setContext(e), a = t.lineWidth, l = t.color, u = r.dash || [], f = r.dashOffset, p = t.tickWidth, m = t.tickColor, h = t.tickBorderDash || [], g = t.tickBorderDashOffset;
			v = js(this, _, s), v !== void 0 && (y = li(n, v, a), c ? b = S = w = E = y : x = C = T = D = y, d.push({
				tx1: b,
				ty1: x,
				tx2: S,
				ty2: C,
				x1: w,
				y1: T,
				x2: E,
				y2: D,
				width: a,
				color: l,
				borderDash: u,
				borderDashOffset: f,
				tickWidth: p,
				tickColor: m,
				tickBorderDash: h,
				tickBorderDashOffset: g
			}));
		}
		return this._ticksLength = l, this._borderValue = g, d;
	}
	_computeLabelItems(e) {
		let t = this.axis, n = this.options, { position: r, ticks: i } = n, a = this.isHorizontal(), o = this.ticks, { align: s, crossAlign: c, padding: l, mirror: u } = i, d = Ns(n.grid), f = d + l, p = u ? -l : f, m = -mr(this.labelRotation), h = [], g, _, v, y, b, x, S, C, w, T, E, D, ee = "middle";
		if (r === "top") x = this.bottom - p, S = this._getXAxisLabelAlignment();
		else if (r === "bottom") x = this.top + p, S = this._getXAxisLabelAlignment();
		else if (r === "left") {
			let e = this._getYAxisLabelAlignment(d);
			S = e.textAlign, b = e.x;
		} else if (r === "right") {
			let e = this._getYAxisLabelAlignment(d);
			S = e.textAlign, b = e.x;
		} else if (t === "x") {
			if (r === "center") x = (e.top + e.bottom) / 2 + f;
			else if (I(r)) {
				let e = Object.keys(r)[0], t = r[e];
				x = this.chart.scales[e].getPixelForValue(t) + f;
			}
			S = this._getXAxisLabelAlignment();
		} else if (t === "y") {
			if (r === "center") b = (e.left + e.right) / 2 - f;
			else if (I(r)) {
				let e = Object.keys(r)[0], t = r[e];
				b = this.chart.scales[e].getPixelForValue(t);
			}
			S = this._getYAxisLabelAlignment(d).textAlign;
		}
		t === "y" && (s === "start" ? ee = "top" : s === "end" && (ee = "bottom"));
		let O = this._getLabelSizes();
		for (g = 0, _ = o.length; g < _; ++g) {
			v = o[g], y = v.label;
			let e = i.setContext(this.getContext(g));
			C = this.getPixelForTick(g) + i.labelOffset, w = this._resolveTickFontOptions(g), T = w.lineHeight, E = F(y) ? y.length : 1;
			let t = E / 2, n = e.color, s = e.textStrokeColor, l = e.textStrokeWidth, d = S;
			a ? (b = C, S === "inner" && (d = g === _ - 1 ? this.options.reverse ? "left" : "right" : g === 0 ? this.options.reverse ? "right" : "left" : "center"), D = r === "top" ? c === "near" || m !== 0 ? -E * T + T / 2 : c === "center" ? -O.highest.height / 2 - t * T + T : -O.highest.height + T / 2 : c === "near" || m !== 0 ? T / 2 : c === "center" ? O.highest.height / 2 - t * T : O.highest.height - E * T, u && (D *= -1), m !== 0 && !e.showLabelBackdrop && (b += T / 2 * Math.sin(m))) : (x = C, D = (1 - E) * T / 2);
			let f;
			if (e.showLabelBackdrop) {
				let t = J(e.backdropPadding), n = O.heights[g], r = O.widths[g], i = D - t.top, a = 0 - t.left;
				switch (ee) {
					case "middle":
						i -= n / 2;
						break;
					case "bottom": i -= n;
				}
				switch (S) {
					case "center":
						a -= r / 2;
						break;
					case "right":
						a -= r;
						break;
					case "inner": g === _ - 1 ? a -= r : g > 0 && (a -= r / 2);
				}
				f = {
					left: a,
					top: i,
					width: r + t.width,
					height: n + t.height,
					color: e.backdropColor
				};
			}
			h.push({
				label: y,
				font: w,
				textOffset: D,
				options: {
					rotation: m,
					color: n,
					strokeColor: s,
					strokeWidth: l,
					textAlign: d,
					textBaseline: ee,
					translation: [b, x],
					backdrop: f
				}
			});
		}
		return h;
	}
	_getXAxisLabelAlignment() {
		let { position: e, ticks: t } = this.options;
		if (-mr(this.labelRotation)) return e === "top" ? "left" : "right";
		let n = "center";
		return t.align === "start" ? n = "left" : t.align === "end" ? n = "right" : t.align === "inner" && (n = "inner"), n;
	}
	_getYAxisLabelAlignment(e) {
		let { position: t, ticks: { crossAlign: n, mirror: r, padding: i } } = this.options, a = this._getLabelSizes(), o = e + i, s = a.widest.width, c, l;
		return t === "left" ? r ? (l = this.right + i, n === "near" ? c = "left" : n === "center" ? (c = "center", l += s / 2) : (c = "right", l += s)) : (l = this.right - o, n === "near" ? c = "right" : n === "center" ? (c = "center", l -= s / 2) : (c = "left", l = this.left)) : t === "right" ? r ? (l = this.left + i, n === "near" ? c = "right" : n === "center" ? (c = "center", l -= s / 2) : (c = "left", l -= s)) : (l = this.left + o, n === "near" ? c = "left" : n === "center" ? (c = "center", l += s / 2) : (c = "right", l = this.right)) : c = "right", {
			textAlign: c,
			x: l
		};
	}
	_computeLabelArea() {
		if (this.options.ticks.mirror) return;
		let e = this.chart, t = this.options.position;
		if (t === "left" || t === "right") return {
			top: 0,
			left: this.left,
			bottom: e.height,
			right: this.right
		};
		if (t === "top" || t === "bottom") return {
			top: this.top,
			left: 0,
			bottom: this.bottom,
			right: e.width
		};
	}
	drawBackground() {
		let { ctx: e, options: { backgroundColor: t }, left: n, top: r, width: i, height: a } = this;
		t && (e.save(), e.fillStyle = t, e.fillRect(n, r, i, a), e.restore());
	}
	getLineWidthForValue(e) {
		let t = this.options.grid;
		if (!this._isVisible() || !t.display) return 0;
		let n = this.ticks.findIndex((t) => t.value === e);
		return n >= 0 ? t.setContext(this.getContext(n)).lineWidth : 0;
	}
	drawGrid(e) {
		let t = this.options.grid, n = this.ctx, r = this._gridLineItems ||= this._computeGridLineItems(e), i, a, o = (e, t, r) => {
			r.width && r.color && (n.save(), n.lineWidth = r.width, n.strokeStyle = r.color, n.setLineDash(r.borderDash || []), n.lineDashOffset = r.borderDashOffset, n.beginPath(), n.moveTo(e.x, e.y), n.lineTo(t.x, t.y), n.stroke(), n.restore());
		};
		if (t.display) for (i = 0, a = r.length; i < a; ++i) {
			let e = r[i];
			t.drawOnChartArea && o({
				x: e.x1,
				y: e.y1
			}, {
				x: e.x2,
				y: e.y2
			}, e), t.drawTicks && o({
				x: e.tx1,
				y: e.ty1
			}, {
				x: e.tx2,
				y: e.ty2
			}, {
				color: e.tickColor,
				width: e.tickWidth,
				borderDash: e.tickBorderDash,
				borderDashOffset: e.tickBorderDashOffset
			});
		}
	}
	drawBorder() {
		let { chart: e, ctx: t, options: { border: n, grid: r } } = this, i = n.setContext(this.getContext()), a = n.display ? i.width : 0;
		if (!a) return;
		let o = r.setContext(this.getContext(0)).lineWidth, s = this._borderValue, c, l, u, d;
		this.isHorizontal() ? (c = li(e, this.left, a) - a / 2, l = li(e, this.right, o) + o / 2, u = d = s) : (u = li(e, this.top, a) - a / 2, d = li(e, this.bottom, o) + o / 2, c = l = s), t.save(), t.lineWidth = i.width, t.strokeStyle = i.color, t.beginPath(), t.moveTo(c, u), t.lineTo(l, d), t.stroke(), t.restore();
	}
	drawLabels(e) {
		if (!this.options.ticks.display) return;
		let t = this.ctx, n = this._computeLabelArea();
		n && mi(t, n);
		let r = this.getLabelItems(e);
		for (let e of r) {
			let n = e.options, r = e.font, i = e.label, a = e.textOffset;
			xi(t, i, 0, a, r, n);
		}
		n && hi(t);
	}
	drawTitle() {
		let { ctx: e, options: { position: t, title: n, reverse: r } } = this;
		if (!n.display) return;
		let i = Y(n.font), a = J(n.padding), o = n.align, s = i.lineHeight / 2;
		t === "bottom" || t === "center" || I(t) ? (s += a.bottom, F(n.text) && (s += i.lineHeight * (n.text.length - 1))) : s += a.top;
		let { titleX: c, titleY: l, maxWidth: u, rotation: d } = Rs(this, s, t, o);
		xi(e, n.text, 0, 0, i, {
			color: n.color,
			maxWidth: u,
			rotation: d,
			textAlign: Ls(o, t, r),
			textBaseline: "middle",
			translation: [c, l],
			strokeColor: n.strokeColor,
			strokeWidth: n.strokeWidth
		});
	}
	draw(e) {
		this._isVisible() && (this.drawBackground(), this.drawGrid(e), this.drawBorder(), this.drawTitle(), this.drawLabels(e));
	}
	_layers() {
		let t = this.options, n = t.ticks && t.ticks.z || 0, r = z(t.grid && t.grid.z, -1), i = z(t.border && t.border.z, 0);
		return !this._isVisible() || this.draw !== e.prototype.draw ? [{
			z: n,
			draw: (e) => {
				this.draw(e);
			}
		}] : [
			{
				z: r,
				draw: (e) => {
					this.drawBackground(), this.drawGrid(e), this.drawTitle();
				}
			},
			{
				z: i,
				draw: () => {
					this.drawBorder();
				}
			},
			{
				z: n,
				draw: (e) => {
					this.drawLabels(e);
				}
			}
		];
	}
	getMatchingVisibleMetas(e) {
		let t = this.chart.getSortedVisibleDatasetMetas(), n = this.axis + "AxisID", r = [], i, a;
		for (i = 0, a = t.length; i < a; ++i) {
			let a = t[i];
			a[n] !== this.id || e && a.type !== e || r.push(a);
		}
		return r;
	}
	_resolveTickFontOptions(e) {
		return Y(this.options.ticks.setContext(this.getContext(e)).font);
	}
	_maxDigits() {
		let e = this._resolveTickFontOptions(0).lineHeight;
		return (this.isHorizontal() ? this.width : this.height) / e;
	}
}, Bs = class {
	constructor(e, t, n) {
		this.type = e, this.scope = t, this.override = n, this.items = Object.create(null);
	}
	isForType(e) {
		return Object.prototype.isPrototypeOf.call(this.type.prototype, e.prototype);
	}
	register(e) {
		let t = Object.getPrototypeOf(e), n;
		Us(t) && (n = this.register(t));
		let r = this.items, i = e.id, a = this.scope + "." + i;
		if (!i) throw Error("class does not have id: " + e);
		return i in r || (r[i] = e, Vs(e, a, n), this.override && q.override(e.id, e.overrides)), a;
	}
	get(e) {
		return this.items[e];
	}
	unregister(e) {
		let t = this.items, n = e.id, r = this.scope;
		n in t && delete t[n], r && n in q[r] && (delete q[r][n], this.override && delete ni[n]);
	}
};
function Vs(e, t, n) {
	let r = Hn(Object.create(null), [
		n ? q.get(n) : {},
		q.get(t),
		e.defaults
	]);
	q.set(t, r), e.defaultRoutes && Hs(t, e.defaultRoutes), e.descriptors && q.describe(t, e.descriptors);
}
function Hs(e, t) {
	Object.keys(t).forEach((n) => {
		let r = n.split("."), i = r.pop(), a = [e].concat(r).join("."), o = t[n].split("."), s = o.pop(), c = o.join(".");
		q.route(a, i, c, s);
	});
}
function Us(e) {
	return "id" in e && "defaults" in e;
}
var Ws = /* #__PURE__ */ new class {
	constructor() {
		this.controllers = new Bs(bo, "datasets", !0), this.elements = new Bs(ys, "elements"), this.plugins = new Bs(Object, "plugins"), this.scales = new Bs(zs, "scales"), this._typedRegistries = [
			this.controllers,
			this.scales,
			this.elements
		];
	}
	add(...e) {
		this._each("register", e);
	}
	remove(...e) {
		this._each("unregister", e);
	}
	addControllers(...e) {
		this._each("register", e, this.controllers);
	}
	addElements(...e) {
		this._each("register", e, this.elements);
	}
	addPlugins(...e) {
		this._each("register", e, this.plugins);
	}
	addScales(...e) {
		this._each("register", e, this.scales);
	}
	getController(e) {
		return this._get(e, this.controllers, "controller");
	}
	getElement(e) {
		return this._get(e, this.elements, "element");
	}
	getPlugin(e) {
		return this._get(e, this.plugins, "plugin");
	}
	getScale(e) {
		return this._get(e, this.scales, "scale");
	}
	removeControllers(...e) {
		this._each("unregister", e, this.controllers);
	}
	removeElements(...e) {
		this._each("unregister", e, this.elements);
	}
	removePlugins(...e) {
		this._each("unregister", e, this.plugins);
	}
	removeScales(...e) {
		this._each("unregister", e, this.scales);
	}
	_each(e, t, n) {
		[...t].forEach((t) => {
			let r = n || this._getRegistryForType(t);
			n || r.isForType(t) || r === this.plugins && t.id ? this._exec(e, r, t) : V(t, (t) => {
				let r = n || this._getRegistryForType(t);
				this._exec(e, r, t);
			});
		});
	}
	_exec(e, t, n) {
		let r = Yn(e);
		B(n["before" + r], [], n), t[e](n), B(n["after" + r], [], n);
	}
	_getRegistryForType(e) {
		for (let t = 0; t < this._typedRegistries.length; t++) {
			let n = this._typedRegistries[t];
			if (n.isForType(e)) return n;
		}
		return this.plugins;
	}
	_get(e, t, n) {
		let r = t.get(e);
		if (r === void 0) throw Error("\"" + e + "\" is not a registered " + n + ".");
		return r;
	}
}(), Gs = class {
	constructor() {
		this._init = void 0;
	}
	notify(e, t, n, r) {
		if (t === "beforeInit" && (this._init = this._createDescriptors(e, !0), this._notify(this._init, e, "install")), this._init === void 0) return;
		let i = r ? this._descriptors(e).filter(r) : this._descriptors(e), a = this._notify(i, e, t, n);
		return t === "afterDestroy" && (this._notify(i, e, "stop"), this._notify(this._init, e, "uninstall"), this._init = void 0), a;
	}
	_notify(e, t, n, r) {
		r ||= {};
		for (let i of e) {
			let e = i.plugin, a = e[n];
			if (B(a, [
				t,
				r,
				i.options
			], e) === !1 && r.cancelable) return !1;
		}
		return !0;
	}
	invalidate() {
		P(this._cache) || (this._oldCache = this._cache, this._cache = void 0);
	}
	_descriptors(e) {
		if (this._cache) return this._cache;
		let t = this._cache = this._createDescriptors(e);
		return this._notifyStateChanges(e), t;
	}
	_createDescriptors(e, t) {
		let n = e && e.config, r = z(n.options && n.options.plugins, {}), i = Ks(n);
		return r === !1 && !t ? [] : Js(e, i, r, t);
	}
	_notifyStateChanges(e) {
		let t = this._oldCache || [], n = this._cache, r = (e, t) => e.filter((e) => !t.some((t) => e.plugin.id === t.plugin.id));
		this._notify(r(t, n), e, "stop"), this._notify(r(n, t), e, "start");
	}
};
function Ks(e) {
	let t = {}, n = [], r = Object.keys(Ws.plugins.items);
	for (let e = 0; e < r.length; e++) n.push(Ws.getPlugin(r[e]));
	let i = e.plugins || [];
	for (let e = 0; e < i.length; e++) {
		let r = i[e];
		n.indexOf(r) === -1 && (n.push(r), t[r.id] = !0);
	}
	return {
		plugins: n,
		localIds: t
	};
}
function qs(e, t) {
	return !t && e === !1 ? null : e === !0 ? {} : e;
}
function Js(e, { plugins: t, localIds: n }, r, i) {
	let a = [], o = e.getContext();
	for (let s of t) {
		let t = s.id, c = qs(r[t], i);
		c !== null && a.push({
			plugin: s,
			options: Ys(e.config, {
				plugin: s,
				local: n[t]
			}, c, o)
		});
	}
	return a;
}
function Ys(e, { plugin: t, local: n }, r, i) {
	let a = e.pluginScopeKeys(t), o = e.getOptionScopes(r, a);
	return n && t.defaults && o.push(t.defaults), e.createResolver(o, i, [""], {
		scriptable: !1,
		indexable: !1,
		allKeys: !0
	});
}
function Xs(e, t) {
	let n = q.datasets[e] || {};
	return ((t.datasets || {})[e] || {}).indexAxis || t.indexAxis || n.indexAxis || "x";
}
function Zs(e, t) {
	let n = e;
	return e === "_index_" ? n = t : e === "_value_" && (n = t === "x" ? "y" : "x"), n;
}
function Qs(e, t) {
	return e === t ? "_index_" : "_value_";
}
function $s(e) {
	if (e === "x" || e === "y" || e === "r") return e;
}
function ec(e) {
	if (e === "top" || e === "bottom") return "x";
	if (e === "left" || e === "right") return "y";
}
function tc(e, ...t) {
	if ($s(e)) return e;
	for (let n of t) {
		let t = n.axis || ec(n.position) || e.length > 1 && $s(e[0].toLowerCase());
		if (t) return t;
	}
	throw Error(`Cannot determine type of '${e}' axis. Please provide 'axis' or 'position' option.`);
}
function nc(e, t, n) {
	if (n[t + "AxisID"] === e) return { axis: t };
}
function rc(e, t) {
	if (t.data && t.data.datasets) {
		let n = t.data.datasets.filter((t) => t.xAxisID === e || t.yAxisID === e);
		if (n.length) return nc(e, "x", n[0]) || nc(e, "y", n[0]);
	}
	return {};
}
function ic(e, t) {
	let n = ni[e.type] || { scales: {} }, r = t.scales || {}, i = Xs(e.type, t), a = Object.create(null);
	return Object.keys(r).forEach((t) => {
		let o = r[t];
		if (!I(o)) return console.error(`Invalid scale configuration for scale: ${t}`);
		if (o._proxy) return console.warn(`Ignoring resolver passed as options for scale: ${t}`);
		let s = tc(t, o, rc(t, e), q.scales[o.type]), c = Qs(s, i), l = n.scales || {};
		a[t] = Un(Object.create(null), [
			{ axis: s },
			o,
			l[s],
			l[c]
		]);
	}), e.data.datasets.forEach((n) => {
		let i = n.type || e.type, o = n.indexAxis || Xs(i, t), s = (ni[i] || {}).scales || {};
		Object.keys(s).forEach((e) => {
			let t = Zs(e, o), i = n[t + "AxisID"] || t;
			a[i] = a[i] || Object.create(null), Un(a[i], [
				{ axis: t },
				r[i],
				s[e]
			]);
		});
	}), Object.keys(a).forEach((e) => {
		let t = a[e];
		Un(t, [q.scales[t.type], q.scale]);
	}), a;
}
function ac(e) {
	let t = e.options ||= {};
	t.plugins = z(t.plugins, {}), t.scales = ic(e, t);
}
function oc(e) {
	return e ||= {}, e.datasets = e.datasets || [], e.labels = e.labels || [], e;
}
function sc(e) {
	return e ||= {}, e.data = oc(e.data), ac(e), e;
}
var cc = /* @__PURE__ */ new Map(), lc = /* @__PURE__ */ new Set();
function uc(e, t) {
	let n = cc.get(e);
	return n || (n = t(), cc.set(e, n), lc.add(n)), n;
}
var dc = (e, t, n) => {
	let r = Jn(t, n);
	r !== void 0 && e.add(r);
}, fc = class {
	constructor(e) {
		this._config = sc(e), this._scopeCache = /* @__PURE__ */ new Map(), this._resolverCache = /* @__PURE__ */ new Map();
	}
	get platform() {
		return this._config.platform;
	}
	get type() {
		return this._config.type;
	}
	set type(e) {
		this._config.type = e;
	}
	get data() {
		return this._config.data;
	}
	set data(e) {
		this._config.data = oc(e);
	}
	get options() {
		return this._config.options;
	}
	set options(e) {
		this._config.options = e;
	}
	get plugins() {
		return this._config.plugins;
	}
	update() {
		let e = this._config;
		this.clearCache(), ac(e);
	}
	clearCache() {
		this._scopeCache.clear(), this._resolverCache.clear();
	}
	datasetScopeKeys(e) {
		return uc(e, () => [[`datasets.${e}`, ""]]);
	}
	datasetAnimationScopeKeys(e, t) {
		return uc(`${e}.transition.${t}`, () => [[`datasets.${e}.transitions.${t}`, `transitions.${t}`], [`datasets.${e}`, ""]]);
	}
	datasetElementScopeKeys(e, t) {
		return uc(`${e}-${t}`, () => [[
			`datasets.${e}.elements.${t}`,
			`datasets.${e}`,
			`elements.${t}`,
			""
		]]);
	}
	pluginScopeKeys(e) {
		let t = e.id, n = this.type;
		return uc(`${n}-plugin-${t}`, () => [[`plugins.${t}`, ...e.additionalOptionScopes || []]]);
	}
	_cachedScopes(e, t) {
		let n = this._scopeCache, r = n.get(e);
		return (!r || t) && (r = /* @__PURE__ */ new Map(), n.set(e, r)), r;
	}
	getOptionScopes(e, t, n) {
		let { options: r, type: i } = this, a = this._cachedScopes(e, n), o = a.get(t);
		if (o) return o;
		let s = /* @__PURE__ */ new Set();
		t.forEach((t) => {
			e && (s.add(e), t.forEach((t) => dc(s, e, t))), t.forEach((e) => dc(s, r, e)), t.forEach((e) => dc(s, ni[i] || {}, e)), t.forEach((e) => dc(s, q, e)), t.forEach((e) => dc(s, ri, e));
		});
		let c = Array.from(s);
		return c.length === 0 && c.push(Object.create(null)), lc.has(t) && a.set(t, c), c;
	}
	chartOptionScopes() {
		let { options: e, type: t } = this;
		return [
			e,
			ni[t] || {},
			q.datasets[t] || {},
			{ type: t },
			q,
			ri
		];
	}
	resolveNamedOptions(e, t, n, r = [""]) {
		let i = { $shared: !0 }, { resolver: a, subPrefixes: o } = pc(this._resolverCache, e, r), s = a;
		if (hc(a, t)) {
			i.$shared = !1, n = Zn(n) ? n() : n;
			let t = this.createResolver(e, n, o);
			s = Pi(a, n, t);
		}
		for (let e of t) i[e] = s[e];
		return i;
	}
	createResolver(e, t, n = [""], r) {
		let { resolver: i } = pc(this._resolverCache, e, n);
		return I(t) ? Pi(i, t, void 0, r) : i;
	}
};
function pc(e, t, n) {
	let r = e.get(t);
	r || (r = /* @__PURE__ */ new Map(), e.set(t, r));
	let i = n.join(), a = r.get(i);
	return a || (a = {
		resolver: Ni(t, n),
		subPrefixes: n.filter((e) => !e.toLowerCase().includes("hover"))
	}, r.set(i, a)), a;
}
var mc = (e) => I(e) && Object.getOwnPropertyNames(e).some((t) => Zn(e[t]));
function hc(e, t) {
	let { isScriptable: n, isIndexable: r } = Fi(e);
	for (let i of t) {
		let t = n(i), a = r(i), o = (a || t) && e[i];
		if (t && (Zn(o) || mc(o)) || a && F(o)) return !0;
	}
	return !1;
}
var gc = "4.6.1", _c = [
	"top",
	"bottom",
	"left",
	"right",
	"chartArea"
];
function vc(e, t) {
	return e === "top" || e === "bottom" || _c.indexOf(e) === -1 && t === "x";
}
function yc(e, t) {
	return function(n, r) {
		return n[e] === r[e] ? n[t] - r[t] : n[e] - r[e];
	};
}
function bc(e) {
	let t = e.chart, n = t.options.animation;
	t.notifyPlugins("afterRender"), B(n && n.onComplete, [e], t);
}
function xc(e) {
	let t = e.chart, n = t.options.animation;
	B(n && n.onProgress, [e], t);
}
function Sc(e) {
	return ca() && typeof e == "string" ? e = document.getElementById(e) : e && e.length && (e = e[0]), e && e.canvas && (e = e.canvas), e;
}
var Cc = {}, wc = (e) => {
	let t = Sc(e);
	return Object.values(Cc).filter((e) => e.canvas === t).pop();
};
function Tc(e, t, n) {
	let r = Object.keys(e);
	for (let i of r) {
		let r = +i;
		if (r >= t) {
			let a = e[i];
			delete e[i], (n > 0 || r > t) && (e[r + n] = a);
		}
	}
}
function Ec(e, t, n, r) {
	return !n || e.type === "mouseout" ? null : r ? t : e;
}
var Dc = class {
	static defaults = q;
	static instances = Cc;
	static overrides = ni;
	static registry = Ws;
	static version = gc;
	static getChart = wc;
	static register(...e) {
		Ws.add(...e), Oc();
	}
	static unregister(...e) {
		Ws.remove(...e), Oc();
	}
	constructor(e, t) {
		let n = this.config = new fc(t), r = Sc(e), i = wc(r);
		if (i) throw Error("Canvas is already in use. Chart with ID '" + i.id + "' must be destroyed before the canvas with ID '" + i.canvas.id + "' can be reused.");
		let a = n.createResolver(n.chartOptionScopes(), this.getContext());
		this.platform = new (n.platform || (vs(r)))(), this.platform.updateConfig(n);
		let o = this.platform.acquireContext(r, a.aspectRatio), s = o && o.canvas, c = s && s.height, l = s && s.width;
		this.id = In(), this.ctx = o, this.canvas = s, this.width = l, this.height = c, this._options = a, this._aspectRatio = this.aspectRatio, this._layers = [], this._metasets = [], this._stacks = void 0, this.boxes = [], this.currentDevicePixelRatio = void 0, this.chartArea = void 0, this._active = [], this._lastEvent = void 0, this._listeners = {}, this._responsiveListeners = void 0, this._sortedMetasets = [], this.scales = {}, this._plugins = new Gs(), this.$proxies = {}, this._hiddenIndices = {}, this.attached = !1, this._animationsDisabled = void 0, this.$context = void 0, this._doResize = Nr((e) => this.update(e), a.resizeDelay || 0), this._dataChanges = [], Cc[this.id] = this, !o || !s ? console.error("Failed to create chart: can't acquire context from the given item") : (qa.listen(this, "complete", bc), qa.listen(this, "progress", xc), this._initialize(), this.attached && this.update());
	}
	get aspectRatio() {
		let { options: { aspectRatio: e, maintainAspectRatio: t }, width: n, height: r, _aspectRatio: i } = this;
		return P(e) ? t && i ? i : r ? n / r : null : e;
	}
	get data() {
		return this.config.data;
	}
	set data(e) {
		this.config.data = e;
	}
	get options() {
		return this._options;
	}
	set options(e) {
		this.config.options = e;
	}
	get registry() {
		return Ws;
	}
	_initialize() {
		return this.notifyPlugins("beforeInit"), this.options.responsive ? this.resize() : xa(this, this.options.devicePixelRatio), this.bindEvents(), this.notifyPlugins("afterInit"), this;
	}
	clear() {
		return ui(this.canvas, this.ctx), this;
	}
	stop() {
		return qa.stop(this), this;
	}
	resize(e, t) {
		qa.running(this) ? this._resizeBeforeDraw = {
			width: e,
			height: t
		} : this._resize(e, t);
	}
	_resize(e, t) {
		let n = this.options, r = this.canvas, i = n.maintainAspectRatio && this.aspectRatio, a = this.platform.getMaximumSize(r, e, t, i), o = n.devicePixelRatio || this.platform.getDevicePixelRatio(), s = this.width ? "resize" : "attach";
		this.width = a.width, this.height = a.height, this._aspectRatio = this.aspectRatio, xa(this, o, !0) && (this.notifyPlugins("resize", { size: a }), B(n.onResize, [this, a], this), this.attached && this._doResize(s) && this.render());
	}
	ensureScalesHaveIDs() {
		V(this.options.scales || {}, (e, t) => {
			e.id = t;
		});
	}
	buildOrUpdateScales() {
		let e = this.options, t = e.scales, n = this.scales, r = Object.keys(n).reduce((e, t) => (e[t] = !1, e), {}), i = [];
		t && (i = i.concat(Object.keys(t).map((e) => {
			let n = t[e], r = tc(e, n), i = r === "r", a = r === "x";
			return {
				options: n,
				dposition: i ? "chartArea" : a ? "bottom" : "left",
				dtype: i ? "radialLinear" : a ? "category" : "linear"
			};
		}))), V(i, (t) => {
			let i = t.options, a = i.id, o = tc(a, i), s = z(i.type, t.dtype);
			(i.position === void 0 || vc(i.position, o) !== vc(t.dposition)) && (i.position = t.dposition), r[a] = !0;
			let c = null;
			a in n && n[a].type === s ? c = n[a] : (c = new (Ws.getScale(s))({
				id: a,
				type: s,
				ctx: this.ctx,
				chart: this
			}), n[c.id] = c), c.init(i, e);
		}), V(r, (e, t) => {
			e || delete n[t];
		}), V(n, (e) => {
			Yo.configure(this, e, e.options), Yo.addBox(this, e);
		});
	}
	_updateMetasets() {
		let e = this._metasets, t = this.data.datasets.length, n = e.length;
		if (e.sort((e, t) => e.index - t.index), n > t) {
			for (let e = t; e < n; ++e) this._destroyDatasetMeta(e);
			e.splice(t, n - t);
		}
		this._sortedMetasets = e.slice(0).sort(yc("order", "index"));
	}
	_removeUnreferencedMetasets() {
		let { _metasets: e, data: { datasets: t } } = this;
		e.length > t.length && delete this._stacks, e.forEach((e, n) => {
			t.filter((t) => t === e._dataset).length === 0 && this._destroyDatasetMeta(n);
		});
	}
	buildOrUpdateControllers() {
		let e = [], t = this.data.datasets, n, r;
		for (this._removeUnreferencedMetasets(), n = 0, r = t.length; n < r; n++) {
			let r = t[n], i = this.getDatasetMeta(n), a = r.type || this.config.type;
			if (i.type && i.type !== a && (this._destroyDatasetMeta(n), i = this.getDatasetMeta(n)), i.type = a, i.indexAxis = r.indexAxis || Xs(a, this.options), i.order = r.order || 0, i.index = n, i.label = "" + r.label, i.visible = this.isDatasetVisible(n), i.controller) i.controller.updateIndex(n), i.controller.linkScales();
			else {
				let t = Ws.getController(a), { datasetElementType: r, dataElementType: o } = q.datasets[a];
				Object.assign(t, {
					dataElementType: Ws.getElement(o),
					datasetElementType: r && Ws.getElement(r)
				}), i.controller = new t(this, n), e.push(i.controller);
			}
		}
		return this._updateMetasets(), e;
	}
	_resetElements() {
		V(this.data.datasets, (e, t) => {
			this.getDatasetMeta(t).controller.reset();
		}, this);
	}
	reset() {
		this._resetElements(), this.notifyPlugins("reset");
	}
	update(e) {
		let t = this.config;
		t.update();
		let n = this._options = t.createResolver(t.chartOptionScopes(), this.getContext()), r = this._animationsDisabled = !n.animation;
		if (this._updateScales(), this._checkEventBindings(), this._updateHiddenIndices(), this._plugins.invalidate(), this.notifyPlugins("beforeUpdate", {
			mode: e,
			cancelable: !0
		}) === !1) return;
		let i = this.buildOrUpdateControllers();
		this.notifyPlugins("beforeElementsUpdate");
		let a = 0;
		for (let e = 0, t = this.data.datasets.length; e < t; e++) {
			let { controller: t } = this.getDatasetMeta(e), n = !r && i.indexOf(t) === -1;
			t.buildOrUpdateElements(n), a = Math.max(+t.getMaxOverflow(), a);
		}
		a = this._minPadding = n.layout.autoPadding ? a : 0, this._updateLayout(a), r || V(i, (e) => {
			e.reset();
		}), this._updateDatasets(e), this.notifyPlugins("afterUpdate", { mode: e }), this._layers.sort(yc("z", "_idx"));
		let { _active: o, _lastEvent: s } = this;
		s ? this._eventHandler(s, !0) : o.length && this._updateHoverStyles(o, o, !0), this.render();
	}
	_updateScales() {
		V(this.scales, (e) => {
			Yo.removeBox(this, e);
		}), this.ensureScalesHaveIDs(), this.buildOrUpdateScales();
	}
	_checkEventBindings() {
		let e = this.options;
		Qn(new Set(Object.keys(this._listeners)), new Set(e.events)) && !!this._responsiveListeners === e.responsive || (this.unbindEvents(), this.bindEvents());
	}
	_updateHiddenIndices() {
		let { _hiddenIndices: e } = this, t = this._getUniformDataChanges() || [];
		for (let { method: n, start: r, count: i } of t) Tc(e, r, n === "_removeElements" ? -i : i);
	}
	_getUniformDataChanges() {
		let e = this._dataChanges;
		if (!e || !e.length) return;
		this._dataChanges = [];
		let t = this.data.datasets.length, n = (t) => new Set(e.filter((e) => e[0] === t).map((e, t) => t + "," + e.splice(1).join(","))), r = n(0);
		for (let e = 1; e < t; e++) if (!Qn(r, n(e))) return;
		return Array.from(r).map((e) => e.split(",")).map((e) => ({
			method: e[1],
			start: +e[2],
			count: +e[3]
		}));
	}
	_updateLayout(e) {
		if (this.notifyPlugins("beforeLayout", { cancelable: !0 }) === !1) return;
		Yo.update(this, this.width, this.height, e);
		let t = this.chartArea, n = t.width <= 0 || t.height <= 0;
		this._layers = [], V(this.boxes, (e) => {
			n && e.position === "chartArea" || (e.configure && e.configure(), this._layers.push(...e._layers()));
		}, this), this._layers.forEach((e, t) => {
			e._idx = t;
		}), this.notifyPlugins("afterLayout");
	}
	_updateDatasets(e) {
		if (this.notifyPlugins("beforeDatasetsUpdate", {
			mode: e,
			cancelable: !0
		}) !== !1) {
			for (let e = 0, t = this.data.datasets.length; e < t; ++e) this.getDatasetMeta(e).controller.configure();
			for (let t = 0, n = this.data.datasets.length; t < n; ++t) this._updateDataset(t, Zn(e) ? e({ datasetIndex: t }) : e);
			this.notifyPlugins("afterDatasetsUpdate", { mode: e });
		}
	}
	_updateDataset(e, t) {
		let n = this.getDatasetMeta(e), r = {
			meta: n,
			index: e,
			mode: t,
			cancelable: !0
		};
		this.notifyPlugins("beforeDatasetUpdate", r) !== !1 && (n.controller._update(t), r.cancelable = !1, this.notifyPlugins("afterDatasetUpdate", r));
	}
	render() {
		this.notifyPlugins("beforeRender", { cancelable: !0 }) !== !1 && (qa.has(this) ? this.attached && !qa.running(this) && qa.start(this) : (this.draw(), bc({ chart: this })));
	}
	draw() {
		let e;
		if (this._resizeBeforeDraw) {
			let { width: e, height: t } = this._resizeBeforeDraw;
			this._resizeBeforeDraw = null, this._resize(e, t);
		}
		if (this.clear(), this.width <= 0 || this.height <= 0 || this.notifyPlugins("beforeDraw", { cancelable: !0 }) === !1) return;
		let t = this._layers;
		for (e = 0; e < t.length && t[e].z <= 0; ++e) t[e].draw(this.chartArea);
		for (this._drawDatasets(); e < t.length; ++e) t[e].draw(this.chartArea);
		this.notifyPlugins("afterDraw");
	}
	_getSortedDatasetMetas(e) {
		let t = this._sortedMetasets, n = [], r, i;
		for (r = 0, i = t.length; r < i; ++r) {
			let i = t[r];
			(!e || i.visible) && n.push(i);
		}
		return n;
	}
	getSortedVisibleDatasetMetas() {
		return this._getSortedDatasetMetas(!0);
	}
	_drawDatasets() {
		if (this.notifyPlugins("beforeDatasetsDraw", { cancelable: !0 }) === !1) return;
		let e = this.getSortedVisibleDatasetMetas();
		for (let t = e.length - 1; t >= 0; --t) this._drawDataset(e[t]);
		this.notifyPlugins("afterDatasetsDraw");
	}
	_drawDataset(e) {
		let t = this.ctx, n = {
			meta: e,
			index: e.index,
			cancelable: !0
		}, r = Ka(this, e);
		this.notifyPlugins("beforeDatasetDraw", n) !== !1 && (r && mi(t, r), e.controller.draw(), r && hi(t), n.cancelable = !1, this.notifyPlugins("afterDatasetDraw", n));
	}
	isPointInArea(e) {
		return pi(e, this.chartArea, this._minPadding);
	}
	getElementsAtEventForMode(e, t, n, r) {
		let i = Mo.modes[t];
		return typeof i == "function" ? i(this, e, n, r) : [];
	}
	getDatasetMeta(e) {
		let t = this.data.datasets[e], n = this._metasets, r = n.filter((e) => e && e._dataset === t).pop();
		return r || (r = {
			type: null,
			data: [],
			dataset: null,
			controller: null,
			hidden: null,
			xAxisID: null,
			yAxisID: null,
			order: t && t.order || 0,
			index: e,
			_dataset: t,
			_parsed: [],
			_sorted: !1
		}, n.push(r)), r;
	}
	getContext() {
		return this.$context ||= Mi(null, {
			chart: this,
			type: "chart"
		});
	}
	getVisibleDatasetCount() {
		return this.getSortedVisibleDatasetMetas().length;
	}
	isDatasetVisible(e) {
		let t = this.data.datasets[e];
		if (!t) return !1;
		let n = this.getDatasetMeta(e);
		return typeof n.hidden == "boolean" ? !n.hidden : !t.hidden;
	}
	setDatasetVisibility(e, t) {
		let n = this.getDatasetMeta(e);
		n.hidden = !t;
	}
	toggleDataVisibility(e) {
		this._hiddenIndices[e] = !this._hiddenIndices[e];
	}
	getDataVisibility(e) {
		return !this._hiddenIndices[e];
	}
	_updateVisibility(e, t, n) {
		let r = n ? "show" : "hide", i = this.getDatasetMeta(e), a = i.controller._resolveAnimations(void 0, r);
		Xn(t) ? (i.data[t].hidden = !n, this.update()) : (this.setDatasetVisibility(e, n), a.update(i, { visible: n }), this.update((t) => t.datasetIndex === e ? r : void 0));
	}
	hide(e, t) {
		this._updateVisibility(e, t, !1);
	}
	show(e, t) {
		this._updateVisibility(e, t, !0);
	}
	_destroyDatasetMeta(e) {
		let t = this._metasets[e];
		t && t.controller && t.controller._destroy(), delete this._metasets[e];
	}
	_stop() {
		let e, t;
		for (this.stop(), qa.remove(this), e = 0, t = this.data.datasets.length; e < t; ++e) this._destroyDatasetMeta(e);
	}
	destroy() {
		this.notifyPlugins("beforeDestroy");
		let { canvas: e, ctx: t } = this;
		this._stop(), this.config.clearCache(), e && (this.unbindEvents(), ui(e, t), this.platform.releaseContext(t), this.canvas = null, this.ctx = null), delete Cc[this.id], this.notifyPlugins("afterDestroy");
	}
	toBase64Image(...e) {
		return this.canvas.toDataURL(...e);
	}
	bindEvents() {
		this.bindUserEvents(), this.options.responsive ? this.bindResponsiveEvents() : this.attached = !0;
	}
	bindUserEvents() {
		let e = this._listeners, t = this.platform, n = (n, r) => {
			t.addEventListener(this, n, r), e[n] = r;
		}, r = (e, t, n) => {
			e.offsetX = t, e.offsetY = n, this._eventHandler(e);
		};
		V(this.options.events, (e) => n(e, r));
	}
	bindResponsiveEvents() {
		this._responsiveListeners ||= {};
		let e = this._responsiveListeners, t = this.platform, n = (n, r) => {
			t.addEventListener(this, n, r), e[n] = r;
		}, r = (n, r) => {
			e[n] && (t.removeEventListener(this, n, r), delete e[n]);
		}, i = (e, t) => {
			this.canvas && this.resize(e, t);
		}, a, o = () => {
			r("attach", o), this.attached = !0, this.resize(), n("resize", i), n("detach", a);
		};
		a = () => {
			this.attached = !1, r("resize", i), this._stop(), this._resize(0, 0), n("attach", o);
		}, t.isAttached(this.canvas) ? o() : a();
	}
	unbindEvents() {
		V(this._listeners, (e, t) => {
			this.platform.removeEventListener(this, t, e);
		}), this._listeners = {}, V(this._responsiveListeners, (e, t) => {
			this.platform.removeEventListener(this, t, e);
		}), this._responsiveListeners = void 0;
	}
	updateHoverStyle(e, t, n) {
		let r = n ? "set" : "remove", i, a, o, s;
		for (t === "dataset" && (i = this.getDatasetMeta(e[0].datasetIndex), i.controller["_" + r + "DatasetHoverStyle"]()), o = 0, s = e.length; o < s; ++o) {
			a = e[o];
			let t = a && this.getDatasetMeta(a.datasetIndex).controller;
			t && t[r + "HoverStyle"](a.element, a.datasetIndex, a.index);
		}
	}
	getActiveElements() {
		return this._active || [];
	}
	setActiveElements(e) {
		let t = this._active || [], n = e.map(({ datasetIndex: e, index: t }) => {
			let n = this.getDatasetMeta(e);
			if (!n) throw Error("No dataset found at index " + e);
			return {
				datasetIndex: e,
				element: n.data[t],
				index: t
			};
		});
		Rn(n, t) || (this._active = n, this._lastEvent = null, this._updateHoverStyles(n, t));
	}
	notifyPlugins(e, t, n) {
		return this._plugins.notify(this, e, t, n);
	}
	isPluginEnabled(e) {
		return this._plugins._cache.filter((t) => t.plugin.id === e).length === 1;
	}
	_updateHoverStyles(e, t, n) {
		let r = this.options.hover, i = (e, t) => e.filter((e) => !t.some((t) => e.datasetIndex === t.datasetIndex && e.index === t.index)), a = i(t, e), o = n ? e : i(e, t);
		a.length && this.updateHoverStyle(a, r.mode, !1), o.length && r.mode && this.updateHoverStyle(o, r.mode, !0);
	}
	_eventHandler(e, t) {
		let n = {
			event: e,
			replay: t,
			cancelable: !0,
			inChartArea: this.isPointInArea(e)
		}, r = (t) => (t.options.events || this.options.events).includes(e.native.type);
		if (this.notifyPlugins("beforeEvent", n, r) === !1) return;
		let i = this._handleEvent(e, t, n.inChartArea);
		return n.cancelable = !1, this.notifyPlugins("afterEvent", n, r), (i || n.changed) && this.render(), this;
	}
	_handleEvent(e, t, n) {
		let { _active: r = [], options: i } = this, a = t, o = this._getActiveElements(e, r, n, a), s = $n(e), c = Ec(e, this._lastEvent, n, s);
		n && (this._lastEvent = null, B(i.onHover, [
			e,
			o,
			this
		], this), s && B(i.onClick, [
			e,
			o,
			this
		], this));
		let l = !Rn(o, r);
		return (l || t) && (this._active = o, this._updateHoverStyles(o, r, t)), this._lastEvent = c, l;
	}
	_getActiveElements(e, t, n, r) {
		if (e.type === "mouseout") return [];
		if (!n) return t;
		let i = this.options.hover;
		return this.getElementsAtEventForMode(e, i.mode, i, r);
	}
};
function Oc() {
	return V(Dc.instances, (e) => e._plugins.invalidate());
}
function kc(e, t, n = t) {
	e.lineCap = z(n.borderCapStyle, t.borderCapStyle), e.setLineDash(z(n.borderDash, t.borderDash)), e.lineDashOffset = z(n.borderDashOffset, t.borderDashOffset), e.lineJoin = z(n.borderJoinStyle, t.borderJoinStyle), e.lineWidth = z(n.borderWidth, t.borderWidth), e.strokeStyle = z(n.borderColor, t.borderColor);
}
function Ac(e, t, n) {
	e.lineTo(n.x, n.y);
}
function jc(e) {
	return e.stepped ? gi : e.tension || e.cubicInterpolationMode === "monotone" ? _i : Ac;
}
function Mc(e, t, n = {}) {
	let r = e.length, { start: i = 0, end: a = r - 1 } = n, { start: o, end: s } = t, c = Math.max(i, o), l = Math.min(a, s), u = i < o && a < o || i > s && a > s;
	return {
		count: r,
		start: c,
		loop: t.loop,
		ilen: l < c && !u ? r + l - c : l - c
	};
}
function Nc(e, t, n, r) {
	let { points: i, options: a } = t, { count: o, start: s, loop: c, ilen: l } = Mc(i, n, r), u = jc(a), { move: d = !0, reverse: f } = r || {}, p, m, h;
	for (p = 0; p <= l; ++p) m = i[(s + (f ? l - p : p)) % o], !m.skip && (d ? (e.moveTo(m.x, m.y), d = !1) : u(e, h, m, f, a.stepped), h = m);
	return c && (m = i[(s + (f ? l : 0)) % o], u(e, h, m, f, a.stepped)), !!c;
}
function Pc(e, t, n, r) {
	let i = t.points, { count: a, start: o, ilen: s } = Mc(i, n, r), { move: c = !0, reverse: l } = r || {}, u = 0, d = 0, f, p, m, h, g, _, v = (e) => (o + (l ? s - e : e)) % a, y = () => {
		h !== g && (e.lineTo(u, g), e.lineTo(u, h), e.lineTo(u, _));
	};
	for (c && (p = i[v(0)], e.moveTo(p.x, p.y)), f = 0; f <= s; ++f) {
		if (p = i[v(f)], p.skip) continue;
		let t = p.x, n = p.y, r = t | 0;
		r === m ? (n < h ? h = n : n > g && (g = n), u = (d * u + t) / ++d) : (y(), e.lineTo(t, n), m = r, d = 0, h = g = n), _ = n;
	}
	y();
}
function Fc(e) {
	let t = e.options, n = t.borderDash && t.borderDash.length;
	return !e._decimated && !e._loop && !t.tension && t.cubicInterpolationMode !== "monotone" && !t.stepped && !n ? Pc : Nc;
}
function Ic(e) {
	return e.stepped ? Ta : e.tension || e.cubicInterpolationMode === "monotone" ? Ea : wa;
}
function Lc(e, t, n, r) {
	let i = t._path;
	i || (i = t._path = new Path2D(), t.path(i, n, r) && i.closePath()), kc(e, t.options), e.stroke(i);
}
function Rc(e, t, n, r) {
	let { segments: i, options: a } = t, o = Fc(t);
	for (let s of i) kc(e, a, s.style), e.beginPath(), o(e, t, s, {
		start: n,
		end: n + r - 1
	}) && e.closePath(), e.stroke();
}
var zc = typeof Path2D == "function";
function Bc(e, t, n, r) {
	zc && !t.options.segment ? Lc(e, t, n, r) : Rc(e, t, n, r);
}
var Vc = class extends ys {
	static id = "line";
	static defaults = {
		borderCapStyle: "butt",
		borderDash: [],
		borderDashOffset: 0,
		borderJoinStyle: "miter",
		borderWidth: 3,
		capBezierPoints: !0,
		cubicInterpolationMode: "default",
		fill: !1,
		spanGaps: !1,
		stepped: !1,
		tension: 0
	};
	static defaultRoutes = {
		backgroundColor: "backgroundColor",
		borderColor: "borderColor"
	};
	static descriptors = {
		_scriptable: !0,
		_indexable: (e) => e !== "borderDash" && e !== "fill"
	};
	constructor(e) {
		super(), this.animated = !0, this.options = void 0, this._chart = void 0, this._loop = void 0, this._fullLoop = void 0, this._path = void 0, this._points = void 0, this._segments = void 0, this._decimated = !1, this._pointsUpdated = !1, this._datasetIndex = void 0, e && Object.assign(this, e);
	}
	updateControlPoints(e, t) {
		let n = this.options;
		if ((n.tension || n.cubicInterpolationMode === "monotone") && !n.stepped && !this._pointsUpdated) {
			let r = n.spanGaps ? this._loop : this._fullLoop;
			sa(this._points, n, e, r, t), this._pointsUpdated = !0;
		}
	}
	set points(e) {
		this._points = e, delete this._segments, delete this._path, this._pointsUpdated = !1;
	}
	get points() {
		return this._points;
	}
	get segments() {
		return this._segments ||= za(this, this.options.segment);
	}
	first() {
		let e = this.segments, t = this.points;
		return e.length && t[e[0].start];
	}
	last() {
		let e = this.segments, t = this.points, n = e.length;
		return n && t[e[n - 1].end];
	}
	interpolate(e, t) {
		let n = this.options, r = e[t], i = this.points, a = Ia(this, {
			property: t,
			start: r,
			end: r
		});
		if (!a.length) return;
		let o = [], s = Ic(n), c, l;
		for (c = 0, l = a.length; c < l; ++c) {
			let { start: l, end: u } = a[c], d = i[l], f = i[u];
			if (d === f) {
				o.push(d);
				continue;
			}
			let p = s(d, f, Math.abs((r - d[t]) / (f[t] - d[t])), n.stepped);
			p[t] = e[t], o.push(p);
		}
		return o.length === 1 ? o[0] : o;
	}
	pathSegment(e, t, n) {
		return Fc(this)(e, this, t, n);
	}
	path(e, t, n) {
		let r = this.segments, i = Fc(this), a = this._loop;
		t ||= 0, n ||= this.points.length - t;
		for (let o of r) a &= i(e, this, o, {
			start: t,
			end: t + n - 1
		});
		return !!a;
	}
	draw(e, t, n, r) {
		let i = this.options || {};
		(this.points || []).length && i.borderWidth && (e.save(), Bc(e, this, n, r), e.restore()), this.animated && (this._pointsUpdated = !1, this._path = void 0);
	}
};
function Hc(e, t, n, r) {
	let i = e.options, { [n]: a } = e.getProps([n], r);
	return Math.abs(t - a) < i.radius + i.hitRadius;
}
var Uc = class extends ys {
	static id = "point";
	parsed;
	skip;
	stop;
	static defaults = {
		borderWidth: 1,
		hitRadius: 1,
		hoverBorderWidth: 1,
		hoverRadius: 4,
		pointStyle: "circle",
		radius: 3,
		rotation: 0
	};
	static defaultRoutes = {
		backgroundColor: "backgroundColor",
		borderColor: "borderColor"
	};
	constructor(e) {
		super(), this.options = void 0, this.parsed = void 0, this.skip = void 0, this.stop = void 0, e && Object.assign(this, e);
	}
	inRange(e, t, n) {
		let r = this.options, { x: i, y: a } = this.getProps(["x", "y"], n);
		return (e - i) ** 2 + (t - a) ** 2 < (r.hitRadius + r.radius) ** 2;
	}
	inXRange(e, t) {
		return Hc(this, e, "x", t);
	}
	inYRange(e, t) {
		return Hc(this, e, "y", t);
	}
	getCenterPoint(e) {
		let { x: t, y: n } = this.getProps(["x", "y"], e);
		return {
			x: t,
			y: n
		};
	}
	size(e) {
		e = e || this.options || {};
		let t = e.radius || 0;
		t = Math.max(t, t && e.hoverRadius || 0);
		let n = t && e.borderWidth || 0;
		return (t + n) * 2;
	}
	draw(e, t) {
		let n = this.options;
		this.skip || n.radius < .1 || !pi(this, t, this.size(n) / 2) || (e.strokeStyle = n.borderColor, e.lineWidth = n.borderWidth, e.fillStyle = n.backgroundColor, di(e, n, this.x, this.y));
	}
	getRange() {
		let e = this.options || {};
		return e.radius + e.hitRadius;
	}
};
function Wc(e, t, n) {
	let r = e.segments, i = e.points, a = t.points, o = [];
	for (let e of r) {
		let { start: r, end: s } = e;
		s = qc(r, s, i);
		let c = Gc(n, i[r], i[s], e.loop);
		if (!t.segments) {
			o.push({
				source: e,
				target: c,
				start: i[r],
				end: i[s]
			});
			continue;
		}
		let l = Ia(t, c);
		for (let t of l) {
			let r = Gc(n, a[t.start], a[t.end], t.loop), s = Fa(e, i, r);
			for (let e of s) o.push({
				source: e,
				target: t,
				start: { [n]: Jc(c, r, "start", Math.max) },
				end: { [n]: Jc(c, r, "end", Math.min) }
			});
		}
	}
	return o;
}
function Gc(e, t, n, r) {
	if (r) return;
	let i = t[e], a = n[e];
	return e === "angle" && (i = G(i), a = G(a)), {
		property: e,
		start: i,
		end: a
	};
}
function Kc(e, t) {
	let { x: n = null, y: r = null } = e || {}, i = t.points, a = [];
	return t.segments.forEach(({ start: e, end: t }) => {
		t = qc(e, t, i);
		let o = i[e], s = i[t];
		r === null ? n !== null && (a.push({
			x: n,
			y: o.y
		}), a.push({
			x: n,
			y: s.y
		})) : (a.push({
			x: o.x,
			y: r
		}), a.push({
			x: s.x,
			y: r
		}));
	}), a;
}
function qc(e, t, n) {
	for (; t > e; t--) {
		let e = n[t];
		if (!isNaN(e.x) && !isNaN(e.y)) break;
	}
	return t;
}
function Jc(e, t, n, r) {
	return e && t ? r(e[n], t[n]) : e ? e[n] : t ? t[n] : 0;
}
function Yc(e, t) {
	let n = [], r = !1;
	return F(e) ? (r = !0, n = e) : n = Kc(e, t), n.length ? new Vc({
		points: n,
		options: { tension: 0 },
		_loop: r,
		_fullLoop: r
	}) : null;
}
function Xc(e) {
	return e && e.fill !== !1;
}
function Zc(e, t, n) {
	let r = e[t].fill, i = [t], a;
	if (!n) return r;
	for (; r !== !1 && i.indexOf(r) === -1;) {
		if (!L(r)) return r;
		if (a = e[r], !a) return !1;
		if (a.visible) return r;
		i.push(r), r = a.fill;
	}
	return !1;
}
function Qc(e, t, n) {
	let r = nl(e);
	if (I(r)) return !isNaN(r.value) && r;
	let i = parseFloat(r);
	return L(i) && Math.floor(i) === i ? $c(r[0], t, i, n) : [
		"origin",
		"start",
		"end",
		"stack",
		"shape"
	].indexOf(r) >= 0 && r;
}
function $c(e, t, n, r) {
	return (e === "-" || e === "+") && (n = t + n), n === t || n < 0 || n >= r ? !1 : n;
}
function el(e, t) {
	let n = null;
	return e === "start" ? n = t.bottom : e === "end" ? n = t.top : I(e) ? n = t.getPixelForValue(e.value) : t.getBasePixel && (n = t.getBasePixel()), n;
}
function tl(e, t, n) {
	let r;
	return r = e === "start" ? n : e === "end" ? t.options.reverse ? t.min : t.max : I(e) ? e.value : t.getBaseValue(), r;
}
function nl(e) {
	let t = e.options, n = t.fill, r = z(n && n.target, n);
	return r === void 0 && (r = !!t.backgroundColor), r === !1 || r === null ? !1 : r === !0 ? "origin" : r;
}
function rl(e) {
	let { scale: t, index: n, line: r } = e, i = [], a = r.segments, o = r.points, s = il(t, n);
	s.push(Yc({
		x: null,
		y: t.bottom
	}, r));
	for (let e = 0; e < a.length; e++) {
		let t = a[e];
		for (let e = t.start; e <= t.end; e++) al(i, o[e], s);
	}
	return new Vc({
		points: i,
		options: {}
	});
}
function il(e, t) {
	let n = [], r = e.getMatchingVisibleMetas("line");
	for (let e = 0; e < r.length; e++) {
		let i = r[e];
		if (i.index === t) break;
		i.hidden || n.unshift(i.dataset);
	}
	return n;
}
function al(e, t, n) {
	let r = [];
	for (let i = 0; i < n.length; i++) {
		let a = n[i], { first: o, last: s, point: c } = ol(a, t, "x");
		if (!(!c || o && s)) {
			if (o) r.unshift(c);
			else if (e.push(c), !s) break;
		}
	}
	e.push(...r);
}
function ol(e, t, n) {
	let r = e.interpolate(t, n);
	if (!r) return {};
	let i = r[n], a = e.segments, o = e.points, s = !1, c = !1;
	for (let e = 0; e < a.length; e++) {
		let t = a[e], r = o[t.start][n], l = o[t.end][n];
		if (Sr(i, r, l)) {
			s = i === r, c = i === l;
			break;
		}
	}
	return {
		first: s,
		last: c,
		point: r
	};
}
var sl = class {
	constructor(e) {
		this.x = e.x, this.y = e.y, this.radius = e.radius;
	}
	pathSegment(e, t, n) {
		let { x: r, y: i, radius: a } = this;
		return t ||= {
			start: 0,
			end: U
		}, e.arc(r, i, a, t.end, t.start, !0), !n.bounds;
	}
	interpolate(e) {
		let { x: t, y: n, radius: r } = this, i = e.angle;
		return {
			x: t + Math.cos(i) * r,
			y: n + Math.sin(i) * r,
			angle: i
		};
	}
};
function cl(e) {
	let { chart: t, fill: n, line: r } = e;
	if (L(n)) return ll(t, n);
	if (n === "stack") return rl(e);
	if (n === "shape") return !0;
	let i = ul(e);
	return i instanceof sl ? i : Yc(i, r);
}
function ll(e, t) {
	let n = e.getDatasetMeta(t);
	return n && e.isDatasetVisible(t) ? n.dataset : null;
}
function ul(e) {
	return (e.scale || {}).getPointPositionForValue ? fl(e) : dl(e);
}
function dl(e) {
	let { scale: t = {}, fill: n } = e, r = el(n, t);
	if (L(r)) {
		let e = t.isHorizontal();
		return {
			x: e ? r : null,
			y: e ? null : r
		};
	}
	return null;
}
function fl(e) {
	let { scale: t, fill: n } = e, r = t.options, i = t.getLabels().length, a = r.reverse ? t.max : t.min, o = tl(n, t, a), s = [];
	if (r.grid.circular) {
		let e = t.getPointPositionForValue(0, a);
		return new sl({
			x: e.x,
			y: e.y,
			radius: t.getDistanceFromCenterForValue(o)
		});
	}
	for (let e = 0; e < i; ++e) s.push(t.getPointPositionForValue(e, o));
	return s;
}
function pl(e, t, n) {
	let r = cl(t), { chart: i, index: a, line: o, scale: s, axis: c } = t, l = o.options, u = l.fill, d = l.backgroundColor, { above: f = d, below: p = d } = u || {}, m = i.getDatasetMeta(a), h = Ka(i, m);
	r && o.points.length && (mi(e, ml(n, m.iScale)), hl(e, {
		line: o,
		target: r,
		above: f,
		below: p,
		area: n,
		scale: s,
		axis: c,
		clip: h
	}), hi(e));
}
function ml(e, t) {
	if (!t || t.getPointPositionForValue) return e;
	let n = t.getPixelForValue(t.min), r = t.getPixelForValue(t.max), i = Math.min(n, r), a = Math.max(n, r);
	return t.isHorizontal() ? {
		...e,
		left: Math.max(e.left, i),
		right: Math.min(e.right, a)
	} : {
		...e,
		top: Math.max(e.top, i),
		bottom: Math.min(e.bottom, a)
	};
}
function hl(e, t) {
	let { line: n, target: r, above: i, below: a, area: o, scale: s, clip: c } = t, l = n._loop ? "angle" : t.axis;
	e.save();
	let u = a;
	a !== i && (l === "x" ? (gl(e, r, o.top), vl(e, {
		line: n,
		target: r,
		color: i,
		scale: s,
		property: l,
		clip: c
	}), e.restore(), e.save(), gl(e, r, o.bottom)) : l === "y" && (_l(e, r, o.left), vl(e, {
		line: n,
		target: r,
		color: a,
		scale: s,
		property: l,
		clip: c
	}), e.restore(), e.save(), _l(e, r, o.right), u = i)), vl(e, {
		line: n,
		target: r,
		color: u,
		scale: s,
		property: l,
		clip: c
	}), e.restore();
}
function gl(e, t, n) {
	let { segments: r, points: i } = t, a = !0, o = !1;
	e.beginPath();
	for (let s of r) {
		let { start: r, end: c } = s, l = i[r], u = i[qc(r, c, i)];
		a ? (e.moveTo(l.x, l.y), a = !1) : (e.lineTo(l.x, n), e.lineTo(l.x, l.y)), o = !!t.pathSegment(e, s, { move: o }), o ? e.closePath() : e.lineTo(u.x, n);
	}
	e.lineTo(t.first().x, n), e.closePath(), e.clip();
}
function _l(e, t, n) {
	let { segments: r, points: i } = t, a = !0, o = !1;
	e.beginPath();
	for (let s of r) {
		let { start: r, end: c } = s, l = i[r], u = i[qc(r, c, i)];
		a ? (e.moveTo(l.x, l.y), a = !1) : (e.lineTo(n, l.y), e.lineTo(l.x, l.y)), o = !!t.pathSegment(e, s, { move: o }), o ? e.closePath() : e.lineTo(n, u.y);
	}
	e.lineTo(n, t.first().y), e.closePath(), e.clip();
}
function vl(e, t) {
	let { line: n, target: r, property: i, color: a, scale: o, clip: s } = t, c = Wc(n, r, i);
	for (let { source: t, target: l, start: u, end: d } of c) {
		let { style: { backgroundColor: c = a } = {} } = t, f = r !== !0;
		e.save(), e.fillStyle = c, yl(e, o, s, f && Gc(i, u, d)), e.beginPath();
		let p = !!n.pathSegment(e, t), m;
		if (f) {
			p ? e.closePath() : bl(e, r, d, i);
			let t = !!r.pathSegment(e, l, {
				move: p,
				reverse: !0
			});
			m = p && t, m || bl(e, r, u, i);
		}
		e.closePath(), e.fill(m ? "evenodd" : "nonzero"), e.restore();
	}
}
function yl(e, t, n, r) {
	let i = t.chart.chartArea, { property: a, start: o, end: s } = r || {};
	if (a === "x" || a === "y") {
		let t, r, c, l;
		a === "x" ? (t = o, r = i.top, c = s, l = i.bottom) : (t = i.left, r = o, c = i.right, l = s), e.beginPath(), n && (t = Math.max(t, n.left), c = Math.min(c, n.right), r = Math.max(r, n.top), l = Math.min(l, n.bottom)), e.rect(t, r, c - t, l - r), e.clip();
	}
}
function bl(e, t, n, r) {
	let i = t.interpolate(n, r);
	i && e.lineTo(i.x, i.y);
}
var xl = {
	id: "filler",
	afterDatasetsUpdate(e, t, n) {
		let r = (e.data.datasets || []).length, i = [], a, o, s, c;
		for (o = 0; o < r; ++o) a = e.getDatasetMeta(o), s = a.dataset, c = null, s && s.options && s instanceof Vc && (c = {
			visible: e.isDatasetVisible(o),
			index: o,
			fill: Qc(s, o, r),
			chart: e,
			axis: a.controller.options.indexAxis,
			scale: a.vScale,
			line: s
		}), a.$filler = c, i.push(c);
		for (o = 0; o < r; ++o) c = i[o], c && c.fill !== !1 && (c.fill = Zc(i, o, n.propagate));
	},
	beforeDraw(e, t, n) {
		let r = n.drawTime === "beforeDraw", i = e.getSortedVisibleDatasetMetas(), a = e.chartArea;
		for (let t = i.length - 1; t >= 0; --t) {
			let n = i[t].$filler;
			n && (n.line.updateControlPoints(a, n.axis), r && n.fill && pl(e.ctx, n, a));
		}
	},
	beforeDatasetsDraw(e, t, n) {
		if (n.drawTime !== "beforeDatasetsDraw") return;
		let r = e.getSortedVisibleDatasetMetas();
		for (let t = r.length - 1; t >= 0; --t) {
			let n = r[t].$filler;
			Xc(n) && pl(e.ctx, n, e.chartArea);
		}
	},
	beforeDatasetDraw(e, t, n) {
		let r = t.meta.$filler;
		Xc(r) && n.drawTime === "beforeDatasetDraw" && pl(e.ctx, r, e.chartArea);
	},
	defaults: {
		propagate: !0,
		drawTime: "beforeDatasetDraw"
	}
}, Sl = {
	average(e) {
		if (!e.length) return !1;
		let t, n, r = /* @__PURE__ */ new Set(), i = 0, a = 0;
		for (t = 0, n = e.length; t < n; ++t) {
			let n = e[t].element;
			if (n && n.hasValue()) {
				let e = n.tooltipPosition();
				r.add(e.x), i += e.y, ++a;
			}
		}
		return a === 0 || r.size === 0 ? !1 : {
			x: [...r].reduce((e, t) => e + t) / r.size,
			y: i / a
		};
	},
	nearest(e, t) {
		if (!e.length) return !1;
		let n = t.x, r = t.y, i = Infinity, a, o, s;
		for (a = 0, o = e.length; a < o; ++a) {
			let n = e[a].element;
			if (n && n.hasValue()) {
				let e = vr(t, n.getCenterPoint());
				e < i && (i = e, s = n);
			}
		}
		if (s) {
			let e = s.tooltipPosition();
			n = e.x, r = e.y;
		}
		return {
			x: n,
			y: r
		};
	}
};
function Cl(e, t) {
	return t && (F(t) ? Array.prototype.push.apply(e, t) : e.push(t)), e;
}
function wl(e) {
	return (typeof e == "string" || e instanceof String) && e.indexOf("\n") > -1 ? e.split("\n") : e;
}
function Tl(e, t) {
	let { element: n, datasetIndex: r, index: i } = t, a = e.getDatasetMeta(r).controller, { label: o, value: s } = a.getLabelAndValue(i);
	return {
		chart: e,
		label: o,
		parsed: a.getParsed(i),
		raw: e.data.datasets[r].data[i],
		formattedValue: s,
		dataset: a.getDataset(),
		dataIndex: i,
		datasetIndex: r,
		element: n
	};
}
function El(e, t) {
	let n = e.chart.ctx, { body: r, footer: i, title: a } = e, { boxWidth: o, boxHeight: s } = t, c = Y(t.bodyFont), l = Y(t.titleFont), u = Y(t.footerFont), d = a.length, f = i.length, p = r.length, m = J(t.padding), h = m.height, g = 0, _ = r.reduce((e, t) => e + t.before.length + t.lines.length + t.after.length, 0);
	if (_ += e.beforeBody.length + e.afterBody.length, d && (h += d * l.lineHeight + (d - 1) * t.titleSpacing + t.titleMarginBottom), _) {
		let e = t.displayColors ? Math.max(s, c.lineHeight) : c.lineHeight;
		h += p * e + (_ - p) * c.lineHeight + (_ - 1) * t.bodySpacing;
	}
	f && (h += t.footerMarginTop + f * u.lineHeight + (f - 1) * t.footerSpacing);
	let v = 0, y = function(e) {
		g = Math.max(g, n.measureText(e).width + v);
	};
	return n.save(), n.font = l.string, V(e.title, y), n.font = c.string, V(e.beforeBody.concat(e.afterBody), y), v = t.displayColors ? o + 2 + t.boxPadding : 0, V(r, (e) => {
		V(e.before, y), V(e.lines, y), V(e.after, y);
	}), v = 0, n.font = u.string, V(e.footer, y), n.restore(), g += m.width, {
		width: g,
		height: h
	};
}
function Dl(e, t) {
	let { y: n, height: r } = t;
	return n < r / 2 ? "top" : n > e.height - r / 2 ? "bottom" : "center";
}
function Ol(e, t, n, r) {
	let { x: i, width: a } = r, o = n.caretSize + n.caretPadding;
	if (e === "left" && i + a + o > t.width || e === "right" && i - a - o < 0) return !0;
}
function kl(e, t, n, r) {
	let { x: i, width: a } = n, { width: o, chartArea: { left: s, right: c } } = e, l = "center";
	return r === "center" ? l = i <= (s + c) / 2 ? "left" : "right" : i <= a / 2 ? l = "left" : i >= o - a / 2 && (l = "right"), Ol(l, e, t, n) && (l = "center"), l;
}
function Al(e, t, n) {
	let r = n.yAlign || t.yAlign || Dl(e, n);
	return {
		xAlign: n.xAlign || t.xAlign || kl(e, t, n, r),
		yAlign: r
	};
}
function jl(e, t) {
	let { x: n, width: r } = e;
	return t === "right" ? n -= r : t === "center" && (n -= r / 2), n;
}
function Ml(e, t, n) {
	let { y: r, height: i } = e;
	return t === "top" ? r += n : r -= t === "bottom" ? i + n : i / 2, r;
}
function Nl(e, t, n, r) {
	let { caretSize: i, caretPadding: a, cornerRadius: o } = e, { xAlign: s, yAlign: c } = n, l = i + a, { topLeft: u, topRight: d, bottomLeft: f, bottomRight: p } = ki(o), m = jl(t, s), h = Ml(t, c, l);
	return c === "center" ? s === "left" ? m += l : s === "right" && (m -= l) : s === "left" ? m -= Math.max(u, f) + i : s === "right" && (m += Math.max(d, p) + i), {
		x: K(m, 0, r.width - t.width),
		y: K(h, 0, r.height - t.height)
	};
}
function Pl(e, t, n) {
	let r = J(n.padding);
	return t === "center" ? e.x + e.width / 2 : t === "right" ? e.x + e.width - r.right : e.x + r.left;
}
function Fl(e) {
	return Cl([], wl(e));
}
function Il(e, t, n) {
	return Mi(e, {
		tooltip: t,
		tooltipItems: n,
		type: "tooltip"
	});
}
function Ll(e, t) {
	let n = t && t.dataset && t.dataset.tooltip && t.dataset.tooltip.callbacks;
	return n ? e.override(n) : e;
}
var Rl = {
	beforeTitle: Fn,
	title(e) {
		if (e.length > 0) {
			let t = e[0], n = t.chart.data.labels, r = n ? n.length : 0;
			if (this && this.options && this.options.mode === "dataset") return t.dataset.label || "";
			if (t.label) return t.label;
			if (r > 0 && t.dataIndex < r) return n[t.dataIndex];
		}
		return "";
	},
	afterTitle: Fn,
	beforeBody: Fn,
	beforeLabel: Fn,
	label(e) {
		if (this && this.options && this.options.mode === "dataset") return e.label + ": " + e.formattedValue || e.formattedValue;
		let t = e.dataset.label || "";
		t && (t += ": ");
		let n = e.formattedValue;
		return P(n) || (t += n), t;
	},
	labelColor(e) {
		let t = e.chart.getDatasetMeta(e.datasetIndex).controller.getStyle(e.dataIndex);
		return {
			borderColor: t.borderColor,
			backgroundColor: t.backgroundColor,
			borderWidth: t.borderWidth,
			borderDash: t.borderDash,
			borderDashOffset: t.borderDashOffset,
			borderRadius: 0
		};
	},
	labelTextColor() {
		return this.options.bodyColor;
	},
	labelPointStyle(e) {
		let t = e.chart.getDatasetMeta(e.datasetIndex).controller.getStyle(e.dataIndex);
		return {
			pointStyle: t.pointStyle,
			rotation: t.rotation
		};
	},
	afterLabel: Fn,
	afterBody: Fn,
	beforeFooter: Fn,
	footer: Fn,
	afterFooter: Fn
};
function X(e, t, n, r) {
	let i = e[t].call(n, r);
	return i === void 0 ? Rl[t].call(n, r) : i;
}
var zl = class extends ys {
	static positioners = Sl;
	constructor(e) {
		super(), this.opacity = 0, this._active = [], this._eventPosition = void 0, this._size = void 0, this._cachedAnimations = void 0, this._tooltipItems = [], this.$animations = void 0, this.$context = void 0, this.chart = e.chart, this.options = e.options, this.dataPoints = void 0, this.title = void 0, this.beforeBody = void 0, this.body = void 0, this.afterBody = void 0, this.footer = void 0, this.xAlign = void 0, this.yAlign = void 0, this.x = void 0, this.y = void 0, this.height = void 0, this.width = void 0, this.caretX = void 0, this.caretY = void 0, this.labelColors = void 0, this.labelPointStyles = void 0, this.labelTextColors = void 0;
	}
	initialize(e) {
		this.options = e, this._cachedAnimations = void 0, this.$context = void 0;
	}
	_resolveAnimations() {
		let e = this._cachedAnimations;
		if (e) return e;
		let t = this.chart, n = this.options.setContext(this.getContext()), r = n.enabled && t.options.animation && n.animations, i = new Za(this.chart, r);
		return r._cacheable && (this._cachedAnimations = Object.freeze(i)), i;
	}
	getContext() {
		return this.$context ||= Il(this.chart.getContext(), this, this._tooltipItems);
	}
	getTitle(e, t) {
		let { callbacks: n } = t, r = X(n, "beforeTitle", this, e), i = X(n, "title", this, e), a = X(n, "afterTitle", this, e), o = [];
		return o = Cl(o, wl(r)), o = Cl(o, wl(i)), o = Cl(o, wl(a)), o;
	}
	getBeforeBody(e, t) {
		return Fl(X(t.callbacks, "beforeBody", this, e));
	}
	getBody(e, t) {
		let { callbacks: n } = t, r = [];
		return V(e, (e) => {
			let t = {
				before: [],
				lines: [],
				after: []
			}, i = Ll(n, e);
			Cl(t.before, wl(X(i, "beforeLabel", this, e))), Cl(t.lines, X(i, "label", this, e)), Cl(t.after, wl(X(i, "afterLabel", this, e))), r.push(t);
		}), r;
	}
	getAfterBody(e, t) {
		return Fl(X(t.callbacks, "afterBody", this, e));
	}
	getFooter(e, t) {
		let { callbacks: n } = t, r = X(n, "beforeFooter", this, e), i = X(n, "footer", this, e), a = X(n, "afterFooter", this, e), o = [];
		return o = Cl(o, wl(r)), o = Cl(o, wl(i)), o = Cl(o, wl(a)), o;
	}
	_createItems(e) {
		let t = this._active, n = this.chart.data, r = [], i = [], a = [], o = [], s, c;
		for (s = 0, c = t.length; s < c; ++s) o.push(Tl(this.chart, t[s]));
		return e.filter && (o = o.filter((t, r, i) => e.filter(t, r, i, n))), e.itemSort && (o = o.sort((t, r) => e.itemSort(t, r, n))), V(o, (t) => {
			let n = Ll(e.callbacks, t);
			r.push(X(n, "labelColor", this, t)), i.push(X(n, "labelPointStyle", this, t)), a.push(X(n, "labelTextColor", this, t));
		}), this.labelColors = r, this.labelPointStyles = i, this.labelTextColors = a, this.dataPoints = o, o;
	}
	update(e, t) {
		let n = this.options.setContext(this.getContext()), r = this._active, i, a = [];
		if (!r.length) this.opacity !== 0 && (i = { opacity: 0 });
		else {
			let e = Sl[n.position].call(this, r, this._eventPosition);
			a = this._createItems(n), this.title = this.getTitle(a, n), this.beforeBody = this.getBeforeBody(a, n), this.body = this.getBody(a, n), this.afterBody = this.getAfterBody(a, n), this.footer = this.getFooter(a, n);
			let t = this._size = El(this, n), o = Object.assign({}, e, t), s = Al(this.chart, n, o), c = Nl(n, o, s, this.chart);
			this.xAlign = s.xAlign, this.yAlign = s.yAlign, i = {
				opacity: 1,
				x: c.x,
				y: c.y,
				width: t.width,
				height: t.height,
				caretX: e.x,
				caretY: e.y
			};
		}
		this._tooltipItems = a, this.$context = void 0, i && this._resolveAnimations().update(this, i), e && n.external && n.external.call(this, {
			chart: this.chart,
			tooltip: this,
			replay: t
		});
	}
	drawCaret(e, t, n, r) {
		let i = this.getCaretPosition(e, n, r);
		t.lineTo(i.x1, i.y1), t.lineTo(i.x2, i.y2), t.lineTo(i.x3, i.y3);
	}
	getCaretPosition(e, t, n) {
		let { xAlign: r, yAlign: i } = this, { caretSize: a, cornerRadius: o } = n, { topLeft: s, topRight: c, bottomLeft: l, bottomRight: u } = ki(o), { x: d, y: f } = e, { width: p, height: m } = t, h, g, _, v, y, b;
		return i === "center" ? (y = f + m / 2, r === "left" ? (h = d, g = h - a, v = y + a, b = y - a) : (h = d + p, g = h + a, v = y - a, b = y + a), _ = h) : (g = r === "left" ? d + Math.max(s, l) + a : r === "right" ? d + p - Math.max(c, u) - a : this.caretX, i === "top" ? (v = f, y = v - a, h = g - a, _ = g + a) : (v = f + m, y = v + a, h = g + a, _ = g - a), b = v), {
			x1: h,
			x2: g,
			x3: _,
			y1: v,
			y2: y,
			y3: b
		};
	}
	drawTitle(e, t, n) {
		let r = this.title, i = r.length, a, o, s;
		if (i) {
			let c = ka(n.rtl, this.x, this.width);
			for (e.x = Pl(this, n.titleAlign, n), t.textAlign = c.textAlign(n.titleAlign), t.textBaseline = "middle", a = Y(n.titleFont), o = n.titleSpacing, t.fillStyle = n.titleColor, t.font = a.string, s = 0; s < i; ++s) t.fillText(r[s], c.x(e.x), e.y + a.lineHeight / 2), e.y += a.lineHeight + o, s + 1 === i && (e.y += n.titleMarginBottom - o);
		}
	}
	_drawColorBox(e, t, n, r, i) {
		let a = this.labelColors[n], o = this.labelPointStyles[n], { boxHeight: s, boxWidth: c } = i, l = Y(i.bodyFont), u = Pl(this, "left", i), d = r.x(u), f = s < l.lineHeight ? (l.lineHeight - s) / 2 : 0, p = t.y + f;
		if (i.usePointStyle) {
			let t = {
				radius: Math.min(c, s) / 2,
				pointStyle: o.pointStyle,
				rotation: o.rotation,
				borderWidth: 1
			}, n = r.leftForLtr(d, c) + c / 2, l = p + s / 2;
			e.strokeStyle = i.multiKeyBackground, e.fillStyle = i.multiKeyBackground, di(e, t, n, l), e.strokeStyle = a.borderColor, e.fillStyle = a.backgroundColor, di(e, t, n, l);
		} else {
			e.lineWidth = I(a.borderWidth) ? Math.max(...Object.values(a.borderWidth)) : a.borderWidth || 1, e.strokeStyle = a.borderColor, e.setLineDash(a.borderDash || []), e.lineDashOffset = a.borderDashOffset || 0;
			let t = r.leftForLtr(d, c), n = r.leftForLtr(r.xPlus(d, 1), c - 2), o = ki(a.borderRadius);
			Object.values(o).some((e) => e !== 0) ? (e.beginPath(), e.fillStyle = i.multiKeyBackground, Si(e, {
				x: t,
				y: p,
				w: c,
				h: s,
				radius: o
			}), e.fill(), e.stroke(), e.fillStyle = a.backgroundColor, e.beginPath(), Si(e, {
				x: n,
				y: p + 1,
				w: c - 2,
				h: s - 2,
				radius: o
			}), e.fill()) : (e.fillStyle = i.multiKeyBackground, e.fillRect(t, p, c, s), e.strokeRect(t, p, c, s), e.fillStyle = a.backgroundColor, e.fillRect(n, p + 1, c - 2, s - 2));
		}
		e.fillStyle = this.labelTextColors[n];
	}
	drawBody(e, t, n) {
		let { body: r } = this, { bodySpacing: i, bodyAlign: a, displayColors: o, boxHeight: s, boxWidth: c, boxPadding: l } = n, u = Y(n.bodyFont), d = u.lineHeight, f = 0, p = ka(n.rtl, this.x, this.width), m = function(n) {
			t.fillText(n, p.x(e.x + f), e.y + d / 2), e.y += d + i;
		}, h = p.textAlign(a), g, _, v, y, b, x, S;
		for (t.textAlign = a, t.textBaseline = "middle", t.font = u.string, e.x = Pl(this, h, n), t.fillStyle = n.bodyColor, V(this.beforeBody, m), f = o && h !== "right" ? a === "center" ? c / 2 + l : c + 2 + l : 0, y = 0, x = r.length; y < x; ++y) {
			for (g = r[y], _ = this.labelTextColors[y], t.fillStyle = _, V(g.before, m), v = g.lines, o && v.length && (this._drawColorBox(t, e, y, p, n), d = Math.max(u.lineHeight, s)), b = 0, S = v.length; b < S; ++b) m(v[b]), d = u.lineHeight;
			V(g.after, m);
		}
		f = 0, d = u.lineHeight, V(this.afterBody, m), e.y -= i;
	}
	drawFooter(e, t, n) {
		let r = this.footer, i = r.length, a, o;
		if (i) {
			let s = ka(n.rtl, this.x, this.width);
			for (e.x = Pl(this, n.footerAlign, n), e.y += n.footerMarginTop, t.textAlign = s.textAlign(n.footerAlign), t.textBaseline = "middle", a = Y(n.footerFont), t.fillStyle = n.footerColor, t.font = a.string, o = 0; o < i; ++o) t.fillText(r[o], s.x(e.x), e.y + a.lineHeight / 2), e.y += a.lineHeight + n.footerSpacing;
		}
	}
	drawBackground(e, t, n, r) {
		let { xAlign: i, yAlign: a } = this, { x: o, y: s } = e, { width: c, height: l } = n, { topLeft: u, topRight: d, bottomLeft: f, bottomRight: p } = ki(r.cornerRadius);
		t.fillStyle = r.backgroundColor, t.strokeStyle = r.borderColor, t.lineWidth = r.borderWidth, t.beginPath(), t.moveTo(o + u, s), a === "top" && this.drawCaret(e, t, n, r), t.lineTo(o + c - d, s), t.quadraticCurveTo(o + c, s, o + c, s + d), a === "center" && i === "right" && this.drawCaret(e, t, n, r), t.lineTo(o + c, s + l - p), t.quadraticCurveTo(o + c, s + l, o + c - p, s + l), a === "bottom" && this.drawCaret(e, t, n, r), t.lineTo(o + f, s + l), t.quadraticCurveTo(o, s + l, o, s + l - f), a === "center" && i === "left" && this.drawCaret(e, t, n, r), t.lineTo(o, s + u), t.quadraticCurveTo(o, s, o + u, s), t.closePath(), t.fill(), r.borderWidth > 0 && t.stroke();
	}
	_updateAnimationTarget(e) {
		let t = this.chart, n = this.$animations, r = n && n.x, i = n && n.y;
		if (r || i) {
			let n = Sl[e.position].call(this, this._active, this._eventPosition);
			if (!n) return;
			let a = this._size = El(this, e), o = Object.assign({}, n, this._size), s = Al(t, e, o), c = Nl(e, o, s, t);
			(r._to !== c.x || i._to !== c.y) && (this.xAlign = s.xAlign, this.yAlign = s.yAlign, this.width = a.width, this.height = a.height, this.caretX = n.x, this.caretY = n.y, this._resolveAnimations().update(this, c));
		}
	}
	_willRender() {
		return !!this.opacity;
	}
	draw(e) {
		let t = this.options.setContext(this.getContext()), n = this.opacity;
		if (!n) return;
		this._updateAnimationTarget(t);
		let r = {
			width: this.width,
			height: this.height
		}, i = {
			x: this.x,
			y: this.y
		};
		n = Math.abs(n) < .001 ? 0 : n;
		let a = J(t.padding), o = this.title.length || this.beforeBody.length || this.body.length || this.afterBody.length || this.footer.length;
		t.enabled && o && (e.save(), e.globalAlpha = n, this.drawBackground(i, e, r, t), Aa(e, t.textDirection), i.y += a.top, this.drawTitle(i, e, t), this.drawBody(i, e, t), this.drawFooter(i, e, t), ja(e, t.textDirection), e.restore());
	}
	getActiveElements() {
		return this._active || [];
	}
	setActiveElements(e, t) {
		let n = this._active, r = e.map(({ datasetIndex: e, index: t }) => {
			let n = this.chart.getDatasetMeta(e);
			if (!n) throw Error("Cannot find a dataset at index " + e);
			return {
				datasetIndex: e,
				element: n.data[t],
				index: t
			};
		}), i = !Rn(n, r), a = this._positionChanged(r, t);
		(i || a) && (this._active = r, this._eventPosition = t, this._ignoreReplayEvents = !0, this.update(!0));
	}
	handleEvent(e, t, n = !0) {
		if (t && this._ignoreReplayEvents) return !1;
		this._ignoreReplayEvents = !1;
		let r = this.options, i = this._active || [], a = this._getActiveElements(e, i, t, n), o = this._positionChanged(a, e), s = t || !Rn(a, i) || o;
		return s && (this._active = a, (r.enabled || r.external) && (this._eventPosition = {
			x: e.x,
			y: e.y
		}, this.update(!0, t))), s;
	}
	_getActiveElements(e, t, n, r) {
		let i = this.options;
		if (e.type === "mouseout") return [];
		if (!r) return t.filter((e) => this.chart.data.datasets[e.datasetIndex] && this.chart.getDatasetMeta(e.datasetIndex).controller.getParsed(e.index) !== void 0);
		let a = this.chart.getElementsAtEventForMode(e, i.mode, i, n);
		return i.reverse && a.reverse(), a;
	}
	_positionChanged(e, t) {
		let { caretX: n, caretY: r, options: i } = this, a = Sl[i.position].call(this, e, t);
		return a !== !1 && (n !== a.x || r !== a.y);
	}
}, Bl = {
	id: "tooltip",
	_element: zl,
	positioners: Sl,
	afterInit(e, t, n) {
		n && (e.tooltip = new zl({
			chart: e,
			options: n
		}));
	},
	beforeUpdate(e, t, n) {
		e.tooltip && e.tooltip.initialize(n);
	},
	reset(e, t, n) {
		e.tooltip && e.tooltip.initialize(n);
	},
	afterDraw(e) {
		let t = e.tooltip;
		if (t && t._willRender()) {
			let n = { tooltip: t };
			if (e.notifyPlugins("beforeTooltipDraw", {
				...n,
				cancelable: !0
			}) === !1) return;
			t.draw(e.ctx), e.notifyPlugins("afterTooltipDraw", n);
		}
	},
	afterEvent(e, t) {
		if (e.tooltip) {
			let n = t.replay;
			e.tooltip.handleEvent(t.event, n, t.inChartArea) && (t.changed = !0);
		}
	},
	defaults: {
		enabled: !0,
		external: null,
		position: "average",
		backgroundColor: "rgba(0,0,0,0.8)",
		titleColor: "#fff",
		titleFont: { weight: "bold" },
		titleSpacing: 2,
		titleMarginBottom: 6,
		titleAlign: "left",
		bodyColor: "#fff",
		bodySpacing: 2,
		bodyFont: {},
		bodyAlign: "left",
		footerColor: "#fff",
		footerSpacing: 2,
		footerMarginTop: 6,
		footerFont: { weight: "bold" },
		footerAlign: "left",
		padding: 6,
		caretPadding: 2,
		caretSize: 5,
		cornerRadius: 6,
		boxHeight: (e, t) => t.bodyFont.size,
		boxWidth: (e, t) => t.bodyFont.size,
		multiKeyBackground: "#fff",
		displayColors: !0,
		boxPadding: 0,
		borderColor: "rgba(0,0,0,0)",
		borderWidth: 0,
		animation: {
			duration: 400,
			easing: "easeOutQuart"
		},
		animations: {
			numbers: {
				type: "number",
				properties: [
					"x",
					"y",
					"width",
					"height",
					"caretX",
					"caretY"
				]
			},
			opacity: {
				easing: "linear",
				duration: 200
			}
		},
		callbacks: Rl
	},
	defaultRoutes: {
		bodyFont: "font",
		footerFont: "font",
		titleFont: "font"
	},
	descriptors: {
		_scriptable: (e) => e !== "filter" && e !== "itemSort" && e !== "external",
		_indexable: !1,
		callbacks: {
			_scriptable: !1,
			_indexable: !1
		},
		animation: { _fallback: !1 },
		animations: { _fallback: "animation" }
	},
	additionalOptionScopes: ["interaction"]
}, Vl = (e, t, n, r) => (typeof t == "string" ? (n = e.push(t) - 1, r.unshift({
	index: n,
	label: t
})) : isNaN(t) && (n = null), n);
function Hl(e, t, n, r) {
	let i = e.indexOf(t);
	return i === -1 ? Vl(e, t, n, r) : i === e.lastIndexOf(t) ? i : n;
}
var Ul = (e, t) => e === null ? null : K(Math.round(e), 0, t);
function Wl(e) {
	let t = this.getLabels();
	return e >= 0 && e < t.length ? t[e] : e;
}
var Gl = class extends zs {
	static id = "category";
	static defaults = { ticks: { callback: Wl } };
	constructor(e) {
		super(e), this._startValue = void 0, this._valueRange = 0, this._addedLabels = [];
	}
	init(e) {
		let t = this._addedLabels;
		if (t.length) {
			let e = this.getLabels();
			for (let { index: n, label: r } of t) e[n] === r && e.splice(n, 1);
			this._addedLabels = [];
		}
		super.init(e);
	}
	parse(e, t) {
		if (P(e)) return null;
		let n = this.getLabels();
		return t = isFinite(t) && n[t] === e ? t : Hl(n, e, z(t, e), this._addedLabels), Ul(t, n.length - 1);
	}
	determineDataLimits() {
		let { minDefined: e, maxDefined: t } = this.getUserBounds(), { min: n, max: r } = this.getMinMax(!0);
		this.options.bounds === "ticks" && (e || (n = 0), t || (r = this.getLabels().length - 1)), this.min = n, this.max = r;
	}
	buildTicks() {
		let e = this.min, t = this.max, n = this.options.offset, r = [], i = this.getLabels();
		i = e === 0 && t === i.length - 1 ? i : i.slice(e, t + 1), this._valueRange = Math.max(i.length - +!n, 1), this._startValue = this.min - (n ? .5 : 0);
		for (let n = e; n <= t; n++) r.push({ value: n });
		return r;
	}
	getLabelForValue(e) {
		return Wl.call(this, e);
	}
	configure() {
		super.configure(), this.isHorizontal() || (this._reversePixels = !this._reversePixels);
	}
	getPixelForValue(e) {
		return typeof e != "number" && (e = this.parse(e)), e === null ? NaN : this.getPixelForDecimal((e - this._startValue) / this._valueRange);
	}
	getPixelForTick(e) {
		let t = this.ticks;
		return e < 0 || e > t.length - 1 ? null : this.getPixelForValue(t[e].value);
	}
	getValueForPixel(e) {
		return Math.round(this._startValue + this.getDecimalForPixel(e) * this._valueRange);
	}
	getBasePixel() {
		return this.bottom;
	}
};
function Kl(e, t) {
	let n = [], { bounds: r, step: i, min: a, max: o, precision: s, count: c, maxTicks: l, maxDigits: u, includeBounds: d } = e, f = i || 1, p = l - 1, { min: m, max: h } = t, g = !P(a), _ = !P(o), v = !P(c), y = (h - m) / (u + 1), b = cr((h - m) / p / f) * f, x, S, C, w;
	if (b < 1e-14 && !g && !_) return [{ value: m }, { value: h }];
	w = Math.ceil(h / b) - Math.floor(m / b), w > p && (b = cr(w * b / p / f) * f), P(s) || (x = 10 ** s, b = Math.ceil(b * x) / x), r === "ticks" ? (S = Math.floor(m / b) * b, C = Math.ceil(h / b) * b) : (S = m, C = h), g && _ && i && fr((o - a) / i, b / 1e3) ? (w = Math.round(Math.min((o - a) / b, l)), b = (o - a) / w, S = a, C = o) : v ? (S = g ? a : S, C = _ ? o : C, w = c - 1, b = (C - S) / w) : (w = (C - S) / b, w = sr(w, Math.round(w), b / 1e3) ? Math.round(w) : Math.ceil(w));
	let T = Math.max(gr(b), gr(S));
	x = 10 ** (P(s) ? T : s), S = Math.round(S * x) / x, C = Math.round(C * x) / x;
	let E = 0;
	for (g && (d && S !== a ? (n.push({ value: a }), S < a && E++, sr(Math.round((S + E * b) * x) / x, a, ql(a, y, e)) && E++) : S < a && E++); E < w; ++E) {
		let e = Math.round((S + E * b) * x) / x;
		if (_ && e > o) break;
		n.push({ value: e });
	}
	return _ && d && C !== o ? n.length && sr(n[n.length - 1].value, o, ql(o, y, e)) ? n[n.length - 1].value = o : n.push({ value: o }) : _ && C !== o || n.push({ value: C }), n;
}
function ql(e, t, { horizontal: n, minRotation: r }) {
	let i = mr(r), a = (n ? Math.sin(i) : Math.cos(i)) || .001, o = .75 * t * ("" + e).length;
	return Math.min(t / a, o);
}
var Jl = class extends zs {
	constructor(e) {
		super(e), this.start = void 0, this.end = void 0, this._startValue = void 0, this._endValue = void 0, this._valueRange = 0;
	}
	parse(e, t) {
		return P(e) || (typeof e == "number" || e instanceof Number) && !isFinite(+e) ? null : +e;
	}
	handleTickRangeOptions() {
		let { beginAtZero: e } = this.options, { minDefined: t, maxDefined: n } = this.getUserBounds(), { min: r, max: i } = this, a = (e) => r = t ? r : e, o = (e) => i = n ? i : e;
		if (e) {
			let e = or(r), t = or(i);
			e < 0 && t < 0 ? o(0) : e > 0 && t > 0 && a(0);
		}
		if (r === i) {
			let t = i === 0 ? 1 : Math.abs(i * .05);
			o(i + t), e || a(r - t);
		}
		this.min = r, this.max = i;
	}
	getTickLimit() {
		let { maxTicksLimit: e, stepSize: t } = this.options.ticks, n;
		return t ? (n = Math.ceil(this.max / t) - Math.floor(this.min / t) + 1, n > 1e3 && (console.warn(`scales.${this.id}.ticks.stepSize: ${t} would result generating up to ${n} ticks. Limiting to 1000.`), n = 1e3)) : (n = this.computeTickLimit(), e ||= 11), e && (n = Math.min(e, n)), n;
	}
	computeTickLimit() {
		return Infinity;
	}
	buildTicks() {
		let e = this.options, t = e.ticks, n = this.getTickLimit();
		n = Math.max(2, n);
		let r = Kl({
			maxTicks: n,
			bounds: e.bounds,
			min: e.min,
			max: e.max,
			precision: t.precision,
			step: t.stepSize,
			count: t.count,
			maxDigits: this._maxDigits(),
			horizontal: this.isHorizontal(),
			minRotation: t.minRotation || 0,
			includeBounds: t.includeBounds !== !1
		}, this._range || this);
		return e.bounds === "ticks" && pr(r, this, "value"), e.reverse ? (r.reverse(), this.start = this.max, this.end = this.min) : (this.start = this.min, this.end = this.max), r;
	}
	configure() {
		let e = this.ticks, t = this.min, n = this.max;
		if (super.configure(), this.options.offset && e.length) {
			let r = (n - t) / Math.max(e.length - 1, 1) / 2;
			t -= r, n += r;
		}
		this._startValue = t, this._endValue = n, this._valueRange = n - t;
	}
	getLabelForValue(e) {
		return Zr(e, this.chart.options.locale, this.options.ticks.format);
	}
}, Yl = class extends Jl {
	static id = "linear";
	static defaults = { ticks: { callback: ei.formatters.numeric } };
	determineDataLimits() {
		let { min: e, max: t } = this.getMinMax(!0);
		this.min = L(e) ? e : 0, this.max = L(t) ? t : 1, this.handleTickRangeOptions();
	}
	computeTickLimit() {
		let e = this.isHorizontal(), t = e ? this.width : this.height, n = mr(this.options.ticks.minRotation), r = (e ? Math.sin(n) : Math.cos(n)) || .001, i = this._resolveTickFontOptions(0);
		return Math.ceil(t / Math.min(40, i.lineHeight / r));
	}
	getPixelForValue(e) {
		return e === null ? NaN : this.getPixelForDecimal((e - this._startValue) / this._valueRange);
	}
	getValueForPixel(e) {
		return this._startValue + this.getDecimalForPixel(e) * this._valueRange;
	}
}, Xl = (e) => Math.floor(ar(e)), Zl = (e, t) => 10 ** (Xl(e) + t);
function Ql(e) {
	return e / 10 ** Xl(e) == 1;
}
function $l(e, t, n) {
	let r = 10 ** n, i = Math.floor(e / r);
	return Math.ceil(t / r) - i;
}
function eu(e, t) {
	let n = Xl(t - e);
	for (; $l(e, t, n) > 10;) n++;
	for (; $l(e, t, n) < 10;) n--;
	return Math.min(n, Xl(e));
}
function tu(e, { min: t, max: n }) {
	t = R(e.min, t);
	let r = [], i = Xl(t), a = eu(t, n), o = a < 0 ? 10 ** Math.abs(a) : 1, s = 10 ** a, c = i > a ? 10 ** i : 0, l = Math.round((t - c) * o) / o, u = Math.floor((t - c) / s / 10) * s * 10, d = Math.floor((l - u) / 10 ** a), f = R(e.min, Math.round((c + u + d * 10 ** a) * o) / o);
	for (; f < n;) r.push({
		value: f,
		major: Ql(f),
		significand: d
	}), d >= 10 ? d = d < 15 ? 15 : 20 : d++, d >= 20 && (a++, d = 2, o = a >= 0 ? 1 : o), f = Math.round((c + u + d * 10 ** a) * o) / o;
	let p = R(e.max, f);
	return r.push({
		value: p,
		major: Ql(p),
		significand: d
	}), r;
}
(class extends zs {
	static id = "logarithmic";
	static defaults = { ticks: {
		callback: ei.formatters.logarithmic,
		major: { enabled: !0 }
	} };
	constructor(e) {
		super(e), this.start = void 0, this.end = void 0, this._startValue = void 0, this._valueRange = 0;
	}
	parse(e, t) {
		let n = Jl.prototype.parse.apply(this, [e, t]);
		if (n === 0) this._zero = !0;
		else return L(n) && n > 0 ? n : null;
	}
	determineDataLimits() {
		let { min: e, max: t } = this.getMinMax(!0);
		this.min = L(e) ? Math.max(0, e) : null, this.max = L(t) ? Math.max(0, t) : null, this.options.beginAtZero && (this._zero = !0), this._zero && this.min !== this._suggestedMin && !L(this._userMin) && (this.min = e === Zl(this.min, 0) ? Zl(this.min, -1) : Zl(this.min, 0)), this.handleTickRangeOptions();
	}
	handleTickRangeOptions() {
		let { minDefined: e, maxDefined: t } = this.getUserBounds(), n = this.min, r = this.max, i = (t) => n = e ? n : t, a = (e) => r = t ? r : e;
		n === r && (n <= 0 ? (i(1), a(10)) : (i(Zl(n, -1)), a(Zl(r, 1)))), n <= 0 && i(Zl(r, -1)), r <= 0 && a(Zl(n, 1)), this.min = n, this.max = r;
	}
	buildTicks() {
		let e = this.options, t = tu({
			min: this._userMin,
			max: this._userMax
		}, this);
		return e.bounds === "ticks" && pr(t, this, "value"), e.reverse ? (t.reverse(), this.start = this.max, this.end = this.min) : (this.start = this.min, this.end = this.max), t;
	}
	getLabelForValue(e) {
		return e === void 0 ? "0" : Zr(e, this.chart.options.locale, this.options.ticks.format);
	}
	configure() {
		let e = this.min;
		super.configure(), this._startValue = ar(e), this._valueRange = ar(this.max) - ar(e);
	}
	getPixelForValue(e) {
		return (e === void 0 || e === 0) && (e = this.min), e === null || isNaN(e) ? NaN : this.getPixelForDecimal(e === this.min ? 0 : (ar(e) - this._startValue) / this._valueRange);
	}
	getValueForPixel(e) {
		let t = this.getDecimalForPixel(e);
		return 10 ** (this._startValue + t * this._valueRange);
	}
});
function nu(e) {
	let t = e.ticks;
	if (t.display && e.display) {
		let e = J(t.backdropPadding);
		return z(t.font && t.font.size, q.font.size) + e.height;
	}
	return 0;
}
function ru(e, t, n) {
	return n = F(n) ? n : [n], {
		w: ci(e, t.string, n),
		h: n.length * t.lineHeight
	};
}
function iu(e, t, n, r, i) {
	return e === r || e === i ? {
		start: t - n / 2,
		end: t + n / 2
	} : e < r || e > i ? {
		start: t - n,
		end: t
	} : {
		start: t,
		end: t + n
	};
}
function au(e) {
	let t = {
		l: e.left + e._padding.left,
		r: e.right - e._padding.right,
		t: e.top + e._padding.top,
		b: e.bottom - e._padding.bottom
	}, n = Object.assign({}, t), r = [], i = [], a = e._pointLabels.length, o = e.options.pointLabels, s = o.centerPointLabels ? H / a : 0;
	for (let c = 0; c < a; c++) {
		let a = o.setContext(e.getPointLabelContext(c));
		i[c] = a.padding;
		let l = e.getPointPosition(c, e.drawingArea + i[c], s), u = Y(a.font), d = ru(e.ctx, u, e._pointLabels[c]);
		r[c] = d;
		let f = G(e.getIndexAngle(c) + s), p = Math.round(hr(f));
		ou(n, t, f, iu(p, l.x, d.w, 0, 180), iu(p, l.y, d.h, 90, 270));
	}
	e.setCenterPoint(t.l - n.l, n.r - t.r, t.t - n.t, n.b - t.b), e._pointLabelItems = lu(e, r, i);
}
function ou(e, t, n, r, i) {
	let a = Math.abs(Math.sin(n)), o = Math.abs(Math.cos(n)), s = 0, c = 0;
	r.start < t.l ? (s = (t.l - r.start) / a, e.l = Math.min(e.l, t.l - s)) : r.end > t.r && (s = (r.end - t.r) / a, e.r = Math.max(e.r, t.r + s)), i.start < t.t ? (c = (t.t - i.start) / o, e.t = Math.min(e.t, t.t - c)) : i.end > t.b && (c = (i.end - t.b) / o, e.b = Math.max(e.b, t.b + c));
}
function su(e, t, n) {
	let r = e.drawingArea, { extra: i, additionalAngle: a, padding: o, size: s } = n, c = e.getPointPosition(t, r + i + o, a), l = Math.round(hr(G(c.angle + W))), u = fu(c.y, s.h, l), d = uu(l), f = du(c.x, s.w, d);
	return {
		visible: !0,
		x: c.x,
		y: u,
		textAlign: d,
		left: f,
		top: u,
		right: f + s.w,
		bottom: u + s.h
	};
}
function cu(e, t) {
	if (!t) return !0;
	let { left: n, top: r, right: i, bottom: a } = e;
	return !(pi({
		x: n,
		y: r
	}, t) || pi({
		x: n,
		y: a
	}, t) || pi({
		x: i,
		y: r
	}, t) || pi({
		x: i,
		y: a
	}, t));
}
function lu(e, t, n) {
	let r = [], i = e._pointLabels.length, a = e.options, { centerPointLabels: o, display: s } = a.pointLabels, c = {
		extra: nu(a) / 2,
		additionalAngle: o ? H / i : 0
	}, l;
	for (let a = 0; a < i; a++) {
		c.padding = n[a], c.size = t[a];
		let i = su(e, a, c);
		r.push(i), s === "auto" && (i.visible = cu(i, l), i.visible && (l = i));
	}
	return r;
}
function uu(e) {
	return e === 0 || e === 180 ? "center" : e < 180 ? "left" : "right";
}
function du(e, t, n) {
	return n === "right" ? e -= t : n === "center" && (e -= t / 2), e;
}
function fu(e, t, n) {
	return n === 90 || n === 270 ? e -= t / 2 : (n > 270 || n < 90) && (e -= t), e;
}
function pu(e, t, n) {
	let { left: r, top: i, right: a, bottom: o } = n, { backdropColor: s } = t;
	if (!P(s)) {
		let n = ki(t.borderRadius), c = J(t.backdropPadding);
		e.fillStyle = s;
		let l = r - c.left, u = i - c.top, d = a - r + c.width, f = o - i + c.height;
		Object.values(n).some((e) => e !== 0) ? (e.beginPath(), Si(e, {
			x: l,
			y: u,
			w: d,
			h: f,
			radius: n
		}), e.fill()) : e.fillRect(l, u, d, f);
	}
}
function mu(e, t) {
	let { ctx: n, options: { pointLabels: r } } = e;
	for (let i = t - 1; i >= 0; i--) {
		let t = e._pointLabelItems[i];
		if (!t.visible) continue;
		let a = r.setContext(e.getPointLabelContext(i));
		pu(n, a, t);
		let o = Y(a.font), { x: s, y: c, textAlign: l } = t;
		xi(n, e._pointLabels[i], s, c + o.lineHeight / 2, o, {
			color: a.color,
			textAlign: l,
			textBaseline: "middle"
		});
	}
}
function hu(e, t, n, r) {
	let { ctx: i } = e;
	if (n) i.arc(e.xCenter, e.yCenter, t, 0, U);
	else {
		let n = e.getPointPosition(0, t);
		i.moveTo(n.x, n.y);
		for (let a = 1; a < r; a++) n = e.getPointPosition(a, t), i.lineTo(n.x, n.y);
	}
}
function gu(e, t, n, r, i) {
	let a = e.ctx, o = t.circular, { color: s, lineWidth: c } = t;
	!o && !r || !s || !c || n < 0 || (a.save(), a.strokeStyle = s, a.lineWidth = c, a.setLineDash(i.dash || []), a.lineDashOffset = i.dashOffset, a.beginPath(), hu(e, n, o, r), a.closePath(), a.stroke(), a.restore());
}
function _u(e, t, n) {
	return Mi(e, {
		label: n,
		index: t,
		type: "pointLabel"
	});
}
(class extends Jl {
	static id = "radialLinear";
	static defaults = {
		display: !0,
		animate: !0,
		position: "chartArea",
		angleLines: {
			display: !0,
			lineWidth: 1,
			borderDash: [],
			borderDashOffset: 0
		},
		grid: { circular: !1 },
		startAngle: 0,
		ticks: {
			showLabelBackdrop: !0,
			callback: ei.formatters.numeric
		},
		pointLabels: {
			backdropColor: void 0,
			backdropPadding: 2,
			display: !0,
			font: { size: 10 },
			callback(e) {
				return e;
			},
			padding: 5,
			centerPointLabels: !1
		}
	};
	static defaultRoutes = {
		"angleLines.color": "borderColor",
		"pointLabels.color": "color",
		"ticks.color": "color"
	};
	static descriptors = { angleLines: { _fallback: "grid" } };
	constructor(e) {
		super(e), this.xCenter = void 0, this.yCenter = void 0, this.drawingArea = void 0, this._pointLabels = [], this._pointLabelItems = [];
	}
	setDimensions() {
		let e = this._padding = J(nu(this.options) / 2), t = this.width = this.maxWidth - e.width, n = this.height = this.maxHeight - e.height;
		this.xCenter = Math.floor(this.left + t / 2 + e.left), this.yCenter = Math.floor(this.top + n / 2 + e.top), this.drawingArea = Math.floor(Math.min(t, n) / 2);
	}
	determineDataLimits() {
		let { min: e, max: t } = this.getMinMax(!1);
		this.min = L(e) && !isNaN(e) ? e : 0, this.max = L(t) && !isNaN(t) ? t : 0, this.handleTickRangeOptions();
	}
	computeTickLimit() {
		return Math.ceil(this.drawingArea / nu(this.options));
	}
	generateTickLabels(e) {
		Jl.prototype.generateTickLabels.call(this, e), this._pointLabels = this.getLabels().map((e, t) => {
			let n = B(this.options.pointLabels.callback, [e, t], this);
			return n || n === 0 ? n : "";
		}).filter((e, t) => this.chart.getDataVisibility(t));
	}
	fit() {
		let e = this.options;
		e.display && e.pointLabels.display ? au(this) : this.setCenterPoint(0, 0, 0, 0);
	}
	setCenterPoint(e, t, n, r) {
		this.xCenter += Math.floor((e - t) / 2), this.yCenter += Math.floor((n - r) / 2), this.drawingArea -= Math.min(this.drawingArea / 2, Math.max(e, t, n, r));
	}
	getIndexAngle(e) {
		let t = U / (this._pointLabels.length || 1), n = this.options.startAngle || 0;
		return G(e * t + mr(n));
	}
	getDistanceFromCenterForValue(e) {
		if (P(e)) return NaN;
		let t = this.drawingArea / (this.max - this.min);
		return this.options.reverse ? (this.max - e) * t : (e - this.min) * t;
	}
	getValueForDistanceFromCenter(e) {
		if (P(e)) return NaN;
		let t = e / (this.drawingArea / (this.max - this.min));
		return this.options.reverse ? this.max - t : this.min + t;
	}
	getPointLabelContext(e) {
		let t = this._pointLabels || [];
		if (e >= 0 && e < t.length) {
			let n = t[e];
			return _u(this.getContext(), e, n);
		}
	}
	getPointPosition(e, t, n = 0) {
		let r = this.getIndexAngle(e) - W + n;
		return {
			x: Math.cos(r) * t + this.xCenter,
			y: Math.sin(r) * t + this.yCenter,
			angle: r
		};
	}
	getPointPositionForValue(e, t) {
		return this.getPointPosition(e, this.getDistanceFromCenterForValue(t));
	}
	getBasePosition(e) {
		return this.getPointPositionForValue(e || 0, this.getBaseValue());
	}
	getPointLabelPosition(e) {
		let { left: t, top: n, right: r, bottom: i } = this._pointLabelItems[e];
		return {
			left: t,
			top: n,
			right: r,
			bottom: i
		};
	}
	drawBackground() {
		let { backgroundColor: e, grid: { circular: t } } = this.options;
		if (e) {
			let n = this.ctx;
			n.save(), n.beginPath(), hu(this, this.getDistanceFromCenterForValue(this._endValue), t, this._pointLabels.length), n.closePath(), n.fillStyle = e, n.fill(), n.restore();
		}
	}
	drawGrid() {
		let e = this.ctx, t = this.options, { angleLines: n, grid: r, border: i } = t, a = this._pointLabels.length, o, s, c;
		if (t.pointLabels.display && mu(this, a), r.display && this.ticks.forEach((e, t) => {
			if (t !== 0 || t === 0 && this.min < 0) {
				s = this.getDistanceFromCenterForValue(e.value);
				let n = this.getContext(t), o = r.setContext(n), c = i.setContext(n);
				gu(this, o, s, a, c);
			}
		}), n.display) {
			for (e.save(), o = a - 1; o >= 0; o--) {
				let r = n.setContext(this.getPointLabelContext(o)), { color: i, lineWidth: a } = r;
				a && i && (e.lineWidth = a, e.strokeStyle = i, e.setLineDash(r.borderDash), e.lineDashOffset = r.borderDashOffset, s = this.getDistanceFromCenterForValue(t.reverse ? this.min : this.max), c = this.getPointPosition(o, s), e.beginPath(), e.moveTo(this.xCenter, this.yCenter), e.lineTo(c.x, c.y), e.stroke());
			}
			e.restore();
		}
	}
	drawBorder() {}
	drawLabels() {
		let e = this.ctx, t = this.options, n = t.ticks;
		if (!n.display) return;
		let r = this.getIndexAngle(0), i, a;
		e.save(), e.translate(this.xCenter, this.yCenter), e.rotate(r), e.textAlign = "center", e.textBaseline = "middle", this.ticks.forEach((r, o) => {
			if (o === 0 && this.min >= 0 && !t.reverse) return;
			let s = n.setContext(this.getContext(o)), c = Y(s.font);
			if (i = this.getDistanceFromCenterForValue(this.ticks[o].value), s.showLabelBackdrop) {
				e.font = c.string, a = e.measureText(r.label).width, e.fillStyle = s.backdropColor;
				let t = J(s.backdropPadding);
				e.fillRect(-a / 2 - t.left, -i - c.size / 2 - t.top, a + t.width, c.size + t.height);
			}
			xi(e, r.label, 0, -i, c, {
				color: s.color,
				strokeColor: s.textStrokeColor,
				strokeWidth: s.textStrokeWidth
			});
		}), e.restore();
	}
	drawTitle() {}
});
var vu = {
	millisecond: {
		common: !0,
		size: 1,
		steps: 1e3
	},
	second: {
		common: !0,
		size: 1e3,
		steps: 60
	},
	minute: {
		common: !0,
		size: 6e4,
		steps: 60
	},
	hour: {
		common: !0,
		size: 36e5,
		steps: 24
	},
	day: {
		common: !0,
		size: 864e5,
		steps: 30
	},
	week: {
		common: !1,
		size: 6048e5,
		steps: 4
	},
	month: {
		common: !0,
		size: 2628e6,
		steps: 12
	},
	quarter: {
		common: !1,
		size: 7884e6,
		steps: 4
	},
	year: {
		common: !0,
		size: 3154e7
	}
}, Z = /* #__PURE__ */ Object.keys(vu);
function yu(e, t) {
	return e - t;
}
function bu(e, t) {
	if (P(t)) return null;
	let n = e._adapter, { parser: r, round: i, isoWeekday: a } = e._parseOpts, o = t;
	return typeof r == "function" && (o = r(o)), L(o) || (o = typeof r == "string" ? n.parse(o, r) : n.parse(o)), o === null ? null : (i && (o = i === "week" && (dr(a) || a === !0) ? n.startOf(o, "isoWeek", a) : n.startOf(o, i)), +o);
}
function xu(e, t, n, r) {
	let i = Z.length;
	for (let a = Z.indexOf(e); a < i - 1; ++a) {
		let e = vu[Z[a]], i = e.steps ? e.steps : 2 ** 53 - 1;
		if (e.common && Math.ceil((n - t) / (i * e.size)) <= r) return Z[a];
	}
	return Z[i - 1];
}
function Su(e, t, n, r, i) {
	for (let a = Z.length - 1; a >= Z.indexOf(n); a--) {
		let n = Z[a];
		if (vu[n].common && e._adapter.diff(i, r, n) >= t - 1) return n;
	}
	return Z[n ? Z.indexOf(n) : 0];
}
function Cu(e) {
	for (let t = Z.indexOf(e) + 1, n = Z.length; t < n; ++t) if (vu[Z[t]].common) return Z[t];
}
function wu(e, t, n) {
	if (!n) e[t] = !0;
	else if (n.length) {
		let { lo: r, hi: i } = Cr(n, t), a = n[r] >= t ? n[r] : n[i];
		e[a] = !0;
	}
}
function Tu(e, t, n, r) {
	let i = e._adapter, a = +i.startOf(t[0].value, r), o = t[t.length - 1].value, s, c;
	for (s = a; s <= o; s = +i.add(s, 1, r)) c = n[s], c >= 0 && (t[c].major = !0);
	return t;
}
function Eu(e, t, n) {
	let r = [], i = {}, a = t.length, o, s;
	for (o = 0; o < a; ++o) s = t[o], i[s] = o, r.push({
		value: s,
		major: !1
	});
	return a === 0 || !n ? r : Tu(e, r, i, n);
}
var Du = class extends zs {
	static id = "time";
	static defaults = {
		bounds: "data",
		adapters: {},
		time: {
			parser: !1,
			unit: !1,
			round: !1,
			isoWeekday: !1,
			minUnit: "millisecond",
			displayFormats: {}
		},
		ticks: {
			source: "auto",
			callback: !1,
			major: { enabled: !1 }
		}
	};
	constructor(e) {
		super(e), this._cache = {
			data: [],
			labels: [],
			all: []
		}, this._unit = "day", this._majorUnit = void 0, this._offsets = {}, this._normalized = !1, this._parseOpts = void 0;
	}
	init(e, t = {}) {
		let n = e.time ||= {}, r = this._adapter = new Co._date(e.adapters.date);
		r.init(t), Un(n.displayFormats, r.formats()), this._parseOpts = {
			parser: n.parser,
			round: n.round,
			isoWeekday: n.isoWeekday
		}, super.init(e), this._normalized = t.normalized;
	}
	parse(e, t) {
		return e === void 0 ? null : bu(this, e);
	}
	beforeLayout() {
		super.beforeLayout(), this._cache = {
			data: [],
			labels: [],
			all: []
		};
	}
	determineDataLimits() {
		let e = this.options, t = this._adapter, n = e.time.unit || "day", { min: r, max: i, minDefined: a, maxDefined: o } = this.getUserBounds();
		function s(e) {
			!a && !isNaN(e.min) && (r = Math.min(r, e.min)), !o && !isNaN(e.max) && (i = Math.max(i, e.max));
		}
		a && o || (s(this._getLabelBounds()), (e.bounds !== "ticks" || e.ticks.source !== "labels") && s(this.getMinMax(!1))), r = L(r) && !isNaN(r) ? r : +t.startOf(Date.now(), n), i = L(i) && !isNaN(i) ? i : +t.endOf(Date.now(), n) + 1, this.min = Math.min(r, i - 1), this.max = Math.max(r + 1, i);
	}
	_getLabelBounds() {
		let e = this.getLabelTimestamps(), t = Infinity, n = -Infinity;
		return e.length && (t = e[0], n = e[e.length - 1]), {
			min: t,
			max: n
		};
	}
	buildTicks() {
		let e = this.options, t = e.time, n = e.ticks, r = n.source === "labels" ? this.getLabelTimestamps() : this._generate();
		e.bounds === "ticks" && r.length && (this.min = this._userMin || r[0], this.max = this._userMax || r[r.length - 1]);
		let i = this.min, a = this.max, o = Er(r, i, a);
		return this._unit = t.unit || (n.autoSkip ? xu(t.minUnit, this.min, this.max, this._getLabelCapacity(i)) : Su(this, o.length, t.minUnit, this.min, this.max)), this._majorUnit = !n.major.enabled || this._unit === "year" ? void 0 : Cu(this._unit), this.initOffsets(r), e.reverse && o.reverse(), Eu(this, o, this._majorUnit);
	}
	afterAutoSkip() {
		this.options.offsetAfterAutoskip && this.initOffsets(this.ticks.map((e) => +e.value));
	}
	initOffsets(e = []) {
		let t = 0, n = 0, r, i;
		this.options.offset && e.length && (r = this.getDecimalForValue(e[0]), t = e.length === 1 ? 1 - r : (this.getDecimalForValue(e[1]) - r) / 2, i = this.getDecimalForValue(e[e.length - 1]), n = e.length === 1 ? i : (i - this.getDecimalForValue(e[e.length - 2])) / 2);
		let a = e.length < 3 ? .5 : .25;
		t = K(t, 0, a), n = K(n, 0, a), this._offsets = {
			start: t,
			end: n,
			factor: 1 / (t + 1 + n)
		};
	}
	_generate() {
		let e = this._adapter, t = this.min, n = this.max, r = this.options, i = r.time, a = i.unit || xu(i.minUnit, t, n, this._getLabelCapacity(t)), o = z(r.ticks.stepSize, 1), s = a === "week" && i.isoWeekday, c = dr(s) || s === !0, l = {}, u = t, d, f;
		if (c && (u = +e.startOf(u, "isoWeek", s)), u = +e.startOf(u, c ? "day" : a), e.diff(n, t, a) > 1e5 * o) throw Error(t + " and " + n + " are too far apart with stepSize of " + o + " " + a);
		let p = r.ticks.source === "data" && this.getDataTimestamps();
		for (d = u, f = 0; d < n; d = +e.add(d, o, a), f++) wu(l, d, p);
		return (d === n || r.bounds === "ticks" || f === 1) && wu(l, d, p), Object.keys(l).sort(yu).map((e) => +e);
	}
	getLabelForValue(e) {
		let t = this._adapter, n = this.options.time;
		return n.tooltipFormat ? t.format(e, n.tooltipFormat) : t.format(e, n.displayFormats.datetime);
	}
	format(e, t) {
		let n = this.options.time.displayFormats, r = this._unit, i = t || n[r];
		return this._adapter.format(e, i);
	}
	_tickFormatFunction(e, t, n, r) {
		let i = this.options, a = i.ticks.callback;
		if (a) return B(a, [
			e,
			t,
			n
		], this);
		let o = i.time.displayFormats, s = this._unit, c = this._majorUnit, l = s && o[s], u = c && o[c], d = n[t], f = c && u && d && d.major;
		return this._adapter.format(e, r || (f ? u : l));
	}
	generateTickLabels(e) {
		let t, n, r;
		for (t = 0, n = e.length; t < n; ++t) r = e[t], r.label = this._tickFormatFunction(r.value, t, e);
	}
	getDecimalForValue(e) {
		return e === null ? NaN : (e - this.min) / (this.max - this.min);
	}
	getPixelForValue(e) {
		let t = this._offsets, n = this.getDecimalForValue(e);
		return this.getPixelForDecimal((t.start + n) * t.factor);
	}
	getValueForPixel(e) {
		let t = this._offsets, n = this.getDecimalForPixel(e) / t.factor - t.end;
		return this.min + n * (this.max - this.min);
	}
	_getLabelSize(e) {
		let t = this.options.ticks, n = this.ctx.measureText(e).width, r = mr(this.isHorizontal() ? t.maxRotation : t.minRotation), i = Math.cos(r), a = Math.sin(r), o = this._resolveTickFontOptions(0).size;
		return {
			w: n * i + o * a,
			h: n * a + o * i
		};
	}
	_getLabelCapacity(e) {
		let t = this.options.time, n = t.displayFormats, r = n[t.unit] || n.millisecond, i = this._tickFormatFunction(e, 0, Eu(this, [e], this._majorUnit), r), a = this._getLabelSize(i), o = Math.floor(this.isHorizontal() ? this.width / a.w : this.height / a.h) - 1;
		return o > 0 ? o : 1;
	}
	getDataTimestamps() {
		let e = this._cache.data || [], t, n;
		if (e.length) return e;
		let r = this.getMatchingVisibleMetas();
		if (this._normalized && r.length) return this._cache.data = r[0].controller.getAllParsedValues(this);
		for (t = 0, n = r.length; t < n; ++t) e = e.concat(r[t].controller.getAllParsedValues(this));
		return this._cache.data = this.normalize(e);
	}
	getLabelTimestamps() {
		let e = this._cache.labels || [], t, n;
		if (e.length) return e;
		let r = this.getLabels();
		for (t = 0, n = r.length; t < n; ++t) e.push(bu(this, r[t]));
		return this._cache.labels = this._normalized ? e : this.normalize(e);
	}
	normalize(e) {
		return Ar(e.sort(yu));
	}
};
function Ou(e, t, n) {
	let r = 0, i = e.length - 1, a, o, s, c;
	n ? (t >= e[r].pos && t <= e[i].pos && ({lo: r, hi: i} = wr(e, "pos", t)), {pos: a, time: s} = e[r], {pos: o, time: c} = e[i]) : (t >= e[r].time && t <= e[i].time && ({lo: r, hi: i} = wr(e, "time", t)), {time: a, pos: s} = e[r], {time: o, pos: c} = e[i]);
	let l = o - a;
	return l ? s + (c - s) * (t - a) / l : s;
}
(class extends Du {
	static id = "timeseries";
	static defaults = Du.defaults;
	constructor(e) {
		super(e), this._table = [], this._minPos = void 0, this._tableRange = void 0;
	}
	initOffsets() {
		let e = this._getTimestampsForTable(), t = this._table = this.buildLookupTable(e);
		this._minPos = Ou(t, this.min), this._tableRange = Ou(t, this.max) - this._minPos, super.initOffsets(e);
	}
	buildLookupTable(e) {
		let { min: t, max: n } = this, r = [], i = [], a, o, s, c, l;
		for (a = 0, o = e.length; a < o; ++a) c = e[a], c >= t && c <= n && r.push(c);
		if (r.length < 2) return [{
			time: t,
			pos: 0
		}, {
			time: n,
			pos: 1
		}];
		for (a = 0, o = r.length; a < o; ++a) l = r[a + 1], s = r[a - 1], c = r[a], Math.round((l + s) / 2) !== c && i.push({
			time: c,
			pos: a / (o - 1)
		});
		return i;
	}
	_generate() {
		let e = this.min, t = this.max, n = super.getDataTimestamps();
		return n.includes(e) && n.length || n.splice(0, 0, e), n.includes(t) && n.length !== 1 || n.push(t), n.sort((e, t) => e - t);
	}
	_getTimestampsForTable() {
		let e = this._cache.all || [];
		if (e.length) return e;
		let t = this.getDataTimestamps(), n = this.getLabelTimestamps();
		return e = t.length && n.length ? this.normalize(t.concat(n)) : t.length ? t : n, e = this._cache.all = e, e;
	}
	getDecimalForValue(e) {
		return (Ou(this._table, e) - this._minPos) / this._tableRange;
	}
	getValueForPixel(e) {
		let t = this._offsets, n = this.getDecimalForPixel(e) / t.factor - t.end;
		return Ou(this._table, n * this._tableRange + this._minPos, !0);
	}
});
// resources/js/hourly-refresh.js
function ku() {
	let e = /* @__PURE__ */ new WeakMap(), t = !1;
	async function n() {
		let n = [...document.querySelectorAll("[data-hourly-refresh]")];
		for (let t of n) e.has(t) || e.set(t, Date.now());
		if (!(t || document.hidden)) {
			t = !0;
			try {
				for (let t of n) {
					let n = e.get(t);
					if (Date.now() - n < 36e5) continue;
					let r = window.Livewire?.find(t.getAttribute("wire:id"));
					if (r) try {
						await r.$refresh(), e.set(t, Date.now());
					} catch {
						e.set(t, Date.now());
					}
				}
			} finally {
				t = !1;
			}
		}
	}
	n(), setInterval(n, 6e4), document.addEventListener("visibilitychange", n), document.addEventListener("livewire:navigated", n);
}
var Q = [
	"revenue",
	"audience",
	"search",
	"development",
	"distribution",
	"content",
	"reliability",
	"page_speed"
];
function Au(e, t, n) {
	return `metribay.groups.v1.${e}.${t}.${n}`;
}
function ju(e) {
	try {
		let t = decodeURIComponent(e.slice(1));
		return t ? document.getElementById(t) : null;
	} catch {
		return null;
	}
}
function Mu(e, t = Q) {
	let n;
	try {
		n = JSON.parse(e);
	} catch {
		return [];
	}
	if (!Array.isArray(n)) return [];
	let r = new Set(t);
	return [...new Set(n.filter((e) => typeof e == "string" && r.has(e)))];
}
var Nu = /* @__PURE__ */ new Map(), Pu = /* @__PURE__ */ new Set(), Fu = !1, Iu = null, Lu = () => {}, Ru = !1;
function zu() {
	try {
		return globalThis.localStorage;
	} catch {
		return null;
	}
}
function Bu(e, t = Q) {
	if (!e) return [];
	if (Fu) return Mu(JSON.stringify(Nu.get(e) ?? []), t);
	let n = zu();
	if (!n) return Fu = !0, [];
	try {
		return Mu(n.getItem(e), t);
	} catch {
		return Fu = !0, Nu.get(e) ?? [];
	}
}
function Vu(e, t, n = Q) {
	let r = Mu(JSON.stringify(t), n);
	if (!e) {
		Nu.set("page", r), Fu = !0;
		return;
	}
	if (Fu) {
		Nu.set(e, r);
		return;
	}
	let i = zu();
	if (!i) Fu = !0, Nu.set(e, r);
	else try {
		i.setItem(e, JSON.stringify(r));
	} catch {
		Fu = !0, Nu.set(e, r);
	}
}
function Hu() {
	return document.querySelector("[data-group-scope]");
}
function Uu(e) {
	let t = (e?.dataset.groupKeys || "").split(",").filter((e) => Q.includes(e));
	return t.length ? t : Q;
}
function Wu(e = Hu()) {
	return !e?.dataset.viewerId || !e.dataset.workspaceId || !e.dataset.projectId ? null : Au(e.dataset.viewerId, e.dataset.workspaceId, e.dataset.projectId);
}
function Gu(e = Hu()) {
	let t = Uu(e);
	return Bu(Wu(e), t).filter((e) => !Pu.has(e));
}
function Ku(e, t) {
	let n = e.querySelector("[data-group-toggle]"), r = e.querySelector("[data-group-body]");
	if (!n || !r) return;
	n.setAttribute("aria-expanded", String(t)), r.hidden = !t;
	let i = n.querySelector(".sr-only"), a = t ? n.dataset.labelExpanded : n.dataset.labelCollapsed;
	i && i.textContent !== a && (i.textContent = a);
}
function qu() {
	let e = [...document.querySelectorAll("[data-group-toggle]")].some((e) => e.getAttribute("aria-expanded") === "false");
	document.querySelectorAll("[data-group-recovery]").forEach((t) => {
		t.hidden = !e;
	});
}
function Ju() {
	let e = Hu();
	if (!e) return;
	let t = Wu(e) ?? "";
	t !== Iu && (Pu.clear(), Iu = t);
	let n = new Set(Gu(e));
	e.querySelectorAll("[data-collapsible]").forEach((e) => Ku(e, !n.has(e.dataset.groupKey))), qu();
}
function Yu(e) {
	let t = e?.closest?.("[data-collapsible]");
	if (!t) return !1;
	let n = t.querySelector("[data-group-toggle]");
	return !n || n.getAttribute("aria-expanded") !== "false" ? !1 : (t.dataset.groupKey && Pu.add(t.dataset.groupKey), Ku(t, !0), qu(), Lu(), !0);
}
function Xu(e, t) {
	let n = Hu(), r = Uu(n), i = new Set(Bu(Wu(n), r));
	t(i), e && Pu.delete(e), Vu(Wu(n), [...i], r);
}
function Zu(e) {
	let t = e.target.closest?.("[data-group-toggle]");
	if (t) {
		let e = t.closest("[data-collapsible]"), n = e?.dataset.groupKey, r = t.getAttribute("aria-expanded") !== "false";
		Xu(n, (e) => {
			r ? e.add(n) : e.delete(n);
		}), e && Ku(e, !r), qu(), Lu();
		return;
	}
	let n = e.target.closest?.("[data-group-expand-all]");
	if (n) {
		let e = document.activeElement === n ? Hu()?.querySelector("[data-group-toggle][aria-expanded=\"false\"]") : null;
		Pu.clear(), Vu(Wu(), [], Uu(Hu())), Ju(), e?.focus(), Lu();
		return;
	}
	if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
	let r = e.target.closest?.("a[href]");
	if (!r || r.target && r.target !== "_self" || r.hasAttribute("download")) return;
	let i;
	try {
		i = new URL(r.href, window.location.href);
	} catch {
		return;
	}
	if (i.origin !== window.location.origin || i.pathname !== window.location.pathname || i.search !== window.location.search || !i.hash) return;
	let a = ju(i.hash);
	a && (i.hash === window.location.hash ? (e.preventDefault(), window.dispatchEvent(new HashChangeEvent("hashchange"))) : Yu(a));
}
function Qu(e = () => {}) {
	Lu = e, !(Ru || typeof document > "u") && (Ru = !0, document.addEventListener("click", Zu));
}
function $u(e, t, n) {
	return `metribay.views.v1.${e}.${t}.${n}`;
}
function ed(e) {
	return String(e ?? "").replace(/[\u0000-\u001F\u007F]/g, "").trim().slice(0, 40);
}
function td(e, t = Q) {
	if (!Array.isArray(e)) return [];
	let n = new Set(t), r = /* @__PURE__ */ new Set();
	for (let t of e) typeof t == "string" && n.has(t) && r.add(t);
	return t.filter((e) => r.has(e));
}
function nd(e, t = Q) {
	let n;
	try {
		n = JSON.parse(e);
	} catch {
		return [];
	}
	if (!Array.isArray(n)) return [];
	let r = [], i = /* @__PURE__ */ new Set(), a = /* @__PURE__ */ new Set();
	for (let e of n) {
		if (!e || typeof e != "object") continue;
		let n = typeof e.id == "string" && /^[a-z0-9]{8,16}$/.test(e.id) ? e.id : "", o = typeof e.name == "string" ? ed(e.name) : "", s = td(e.groups, t);
		if (n && o && s.length !== 0 && !a.has(n) && !i.has(o) && (a.add(n), i.add(o), r.push({
			id: n,
			name: o,
			groups: s
		}), r.length === 12)) break;
	}
	return r;
}
function rd() {
	let e = /* @__PURE__ */ new Uint8Array(12);
	if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(e);
	else for (let t = 0; t < e.length; t++) e[t] = Math.floor(Math.random() * 256);
	return [...e].map((e) => "abcdefghijklmnopqrstuvwxyz0123456789"[e % 36]).join("");
}
function id(e, t, n = Q, r = 12) {
	let i = ed(t?.name), a = td(t?.groups, n);
	if (!i) return {
		views: e,
		error: "name",
		saved: null
	};
	if (a.length === 0) return {
		views: e,
		error: "groups",
		saved: null
	};
	let o = e.findIndex((e) => e.name === i);
	if (o === -1 && e.length >= r) return {
		views: e,
		error: "limit",
		saved: null
	};
	let s = {
		id: o === -1 ? typeof t.id == "string" && /^[a-z0-9]{8,16}$/.test(t.id) ? t.id : rd() : e[o].id,
		name: i,
		groups: a
	}, c = e.slice();
	return o === -1 ? c.push(s) : c[o] = s, {
		views: c,
		error: null,
		saved: s
	};
}
function ad(e, t, n) {
	let r = ed(n);
	if (!r) return {
		views: e,
		error: "name"
	};
	let i = e.findIndex((e) => e.id === t);
	if (i === -1) return {
		views: e,
		error: "missing"
	};
	if (e.some((e) => e.id !== t && e.name === r)) return {
		views: e,
		error: "taken"
	};
	let a = e.slice();
	return a[i] = {
		...a[i],
		name: r
	}, {
		views: a,
		error: null
	};
}
function od(e, t) {
	return e.filter((e) => e.id !== t);
}
function sd(e, t) {
	let n = e instanceof URL ? e : new URL(e, "http://localhost"), r = new URL(t, n);
	if (r.pathname !== n.pathname) return !1;
	let i = /* @__PURE__ */ new Set([...r.searchParams.keys(), ...n.searchParams.keys()]);
	for (let e of i) {
		let t = r.searchParams.getAll(e), i = n.searchParams.getAll(e);
		if (t.length !== i.length || t.some((e, t) => e !== i[t])) return !1;
	}
	return !0;
}
function cd(e, t) {
	let n = td(e), r = td(t);
	return n.length === r.length && n.every((e, t) => e === r[t]);
}
function ld(e, t, n) {
	let r = new URL(e, "http://localhost"), i = new FormData(t);
	for (let [e, t] of i) e !== "groups" && e !== "groups[]" && t !== "" && r.searchParams.append(e, t);
	let a = td(n);
	return a.length && r.searchParams.set("groups", a.join(",")), `${r.pathname}${r.search}`;
}
var ud = /* @__PURE__ */ new Map(), dd = !1, fd = !1;
function pd() {
	try {
		return globalThis.localStorage;
	} catch {
		return null;
	}
}
function md(e, t = Q) {
	if (!e) return [];
	if (dd) return nd(JSON.stringify(ud.get(e) ?? []), t);
	let n = pd();
	if (!n) return dd = !0, [];
	try {
		return nd(n.getItem(e), t);
	} catch {
		return dd = !0, ud.get(e) ?? [];
	}
}
function hd(e, t, n = Q) {
	let r = nd(JSON.stringify(t), n);
	if (!e) return ud.set("page", r), dd = !0, r;
	if (dd) return ud.set(e, r), r;
	let i = pd();
	if (!i) return dd = !0, ud.set(e, r), r;
	try {
		i.setItem(e, JSON.stringify(r));
	} catch {
		dd = !0, ud.set(e, r);
	}
	return r;
}
function gd(e) {
	return e.closest("[data-group-scope]");
}
function _d(e) {
	let t = gd(e);
	return !t?.dataset.viewerId || !t.dataset.workspaceId || !t.dataset.projectId ? null : $u(t.dataset.viewerId, t.dataset.workspaceId, t.dataset.projectId);
}
function vd(e) {
	let t = (e.dataset.knownGroups || "").split(",").filter((e) => Q.includes(e));
	return t.length ? t : Q;
}
function yd(e) {
	return [...e.querySelectorAll("[data-group]:checked")].map((e) => e.value);
}
function bd(e, t, n = {}) {
	let r = e.dataset[t] || "";
	for (let [e, t] of Object.entries(n)) r = r.replaceAll(`:${e}`, t);
	let i = e.querySelector("[data-view-status]");
	return i && (i.textContent = r), r;
}
function xd(e) {
	return td((e.dataset.activeGroups || "").split(","));
}
function Sd(e, t, n) {
	let r = e.querySelector("[data-saved-link]"), i = e.querySelector("[data-saved-input]"), a = n.querySelector("[data-custom-view-form]");
	r.href = ld(a.action, a, t.groups), r.textContent = t.name, i.value = t.name, e.dataset.id = t.id, n.dataset.match === "custom" && cd(t.groups, xd(n)) ? r.setAttribute("aria-current", "page") : r.removeAttribute("aria-current");
}
function Cd() {
	document.activeElement?.matches?.("[data-saved-input], [data-view-name]") || document.querySelectorAll("[data-project-views]").forEach((e) => {
		let t = e.querySelector("[data-saved-views]"), n = e.querySelector("[data-saved-view-template]"), r = e.querySelector("[data-saved-empty]"), i = e.querySelector("[data-saved-heading]"), a = e.querySelector("[data-custom-view-form]");
		if (!t || !n || !a) return;
		let o = md(_d(e)), s = JSON.stringify([
			e.dataset.match,
			e.dataset.activeGroups,
			ld(a.action, a, []),
			o
		]);
		if (t.dataset.rendered === s) return;
		t.dataset.rendered = s, t.replaceChildren();
		for (let r of o) {
			let i = n.content.firstElementChild.cloneNode(!0);
			Sd(i, r, e), t.append(i);
		}
		r && (r.hidden = o.length > 0), i && (i.hidden = o.length === 0);
		let c = e.querySelector("summary"), l = o.find((t) => e.dataset.match === "custom" && cd(t.groups, xd(e)));
		c && e.dataset.summaryLabel && (c.textContent = l ? l.name : e.dataset.summaryLabel);
	});
}
function wd(e) {
	let t = e.target.closest?.("[data-project-views]");
	if (!t) return;
	let n = e.target.closest("[data-saved-rename]"), r = e.target.closest("[data-saved-rename-save]"), i = e.target.closest("[data-saved-remove]"), a = e.target.closest("[data-view-save]");
	if (n) {
		let e = n.closest("[data-saved-row]");
		e.querySelector("[data-saved-link]").hidden = !0;
		let t = e.querySelector("[data-saved-input]");
		t.hidden = !1, n.hidden = !0, e.querySelector("[data-saved-rename-save]").hidden = !1, t.focus(), t.select();
		return;
	}
	if (r) {
		let e = r.closest("[data-saved-row]"), n = _d(t), i = ad(md(n), e.dataset.id, e.querySelector("[data-saved-input]").value);
		if (i.error) {
			bd(t, i.error === "taken" ? "msgTaken" : "msgName"), e.querySelector("[data-saved-input]").focus();
			return;
		}
		hd(n, i.views), bd(t, "msgRenamed"), document.activeElement?.matches?.("[data-saved-input]") && document.activeElement.blur(), Cd();
		return;
	}
	if (i) {
		let e = i.closest("[data-saved-row]"), n = _d(t);
		hd(n, od(md(n), e.dataset.id)), bd(t, "msgRemoved"), Cd();
		return;
	}
	if (!a) return;
	let o = vd(t), s = _d(t), c = t.querySelector("[data-view-name]")?.value ?? "", l = id(md(s), {
		name: c,
		groups: yd(t)
	}, o, Number(t.dataset.max) || 12);
	if (l.error) {
		bd(t, l.error === "limit" ? "msgLimit" : l.error === "groups" ? "msgGroups" : "msgName", { count: t.dataset.max || "12" }), t.querySelector(l.error === "groups" ? "[data-group]" : "[data-view-name]")?.focus();
		return;
	}
	hd(s, l.views);
	let u = t.querySelector("[data-custom-view-form]"), d = ld(u.action, u, l.saved.groups);
	if (!sd(window.location.href, d)) {
		window.location.assign(d);
		return;
	}
	bd(t, "msgSaved");
	let f = t.querySelector("[data-view-name]");
	f && (f.value = "", document.activeElement === f && f.blur()), Cd();
}
function Td(e) {
	let t = e.target;
	if (!t?.matches?.("[data-custom-view-form]")) return;
	e.preventDefault();
	let n = yd(t.closest("[data-project-views]"));
	window.location.assign(ld(t.action, t, n));
}
function Ed(e) {
	if (e.target.matches?.("[data-view-name]") && e.key === "Enter") {
		e.preventDefault(), e.stopPropagation(), e.target.closest("[data-project-views]")?.querySelector("[data-view-save]")?.click();
		return;
	}
	let t = e.target.closest?.("[data-saved-input]");
	if (!t || !["Enter", "Escape"].includes(e.key)) return;
	e.stopPropagation();
	let n = t.closest("[data-saved-row]");
	e.preventDefault(), e.key === "Escape" ? (t.value = n.querySelector("[data-saved-link]").textContent, t.hidden = !0, n.querySelector("[data-saved-link]").hidden = !1, n.querySelector("[data-saved-rename]").hidden = !1, n.querySelector("[data-saved-rename-save]").hidden = !0, n.querySelector("[data-saved-rename]").focus()) : n.querySelector("[data-saved-rename-save]").click();
}
function Dd() {
	Cd(), !(fd || typeof document > "u") && (fd = !0, document.addEventListener("click", wd), document.addEventListener("submit", Td), document.addEventListener("keydown", Ed, !0));
}
// resources/js/app.js
Dc.register(xo, Vc, Uc, Gl, Yl, Bl, xl);
var Od = document.documentElement, kd = /* @__PURE__ */ new Set(), Ad = {
	get(e, t) {
		try {
			return localStorage.getItem(`metribay.${e}`) || t;
		} catch {
			return t;
		}
	},
	set(e, t) {
		try {
			localStorage.setItem(`metribay.${e}`, t);
		} catch {}
	}
}, jd = window.matchMedia("(prefers-color-scheme: dark)"), Md = !1, Nd = {};
try {
	Nd = JSON.parse(document.getElementById("metribay-i18n")?.textContent || "{}");
} catch {}
function $(e, t = {}) {
	let n = Nd[e] ?? e;
	for (let [e, r] of Object.entries(t)) n = n.replaceAll(`:${e}`, r);
	return n;
}
function Pd() {
	let e = Ad.get("theme", "system");
	return [
		"light",
		"dark",
		"system"
	].includes(e) ? e : "system";
}
function Fd(e = Pd(), t = !1) {
	[
		"light",
		"dark",
		"system"
	].includes(e) && (t && Ad.set("theme", e), Od.dataset.theme = e === "system" ? jd.matches ? "dark" : "light" : e, document.querySelectorAll("[data-theme-input]").forEach((t) => {
		t.checked = t.value === e;
	}), document.querySelectorAll("[data-theme-cycle]").forEach((t) => {
		let n = e === "system" ? Od.dataset.theme === "light" ? "dark" : "light" : {
			light: "dark",
			dark: "system"
		}[e], r = {
			light: $("Light"),
			dark: $("Dark"),
			system: $("System")
		}, i = $("Theme: :current. Switch to :next.", {
			current: r[e],
			next: r[n]
		});
		t.title = i, t.setAttribute("aria-label", i), t.dataset.currentTheme = e;
	}), tf());
}
jd.addEventListener("change", () => {
	Pd() === "system" && Fd();
});
function Id(e) {
	return e != null && e !== "" && Number.isFinite(Number(e)) ? Number(e) : null;
}
function Ld(e, t = "count", n = !1, r = null) {
	if (e === null) return $("Unavailable");
	if (t === "status") return {
		0: $("Paused"),
		1: $("Not checked yet"),
		2: $("Up"),
		8: $("Seems down"),
		9: $("Down")
	}[e] ?? String(e);
	if (t === "currency" && r) return new Intl.NumberFormat(document.documentElement.lang || "en", {
		style: "currency",
		currency: r,
		notation: n && Math.abs(e) >= 1e3 ? "compact" : "standard"
	}).format(e);
	let i = { maximumFractionDigits: t === "score" ? 0 : t === "ratio" ? 3 : t === "percent" ? 2 : +!Number.isInteger(e) };
	n && Math.abs(e) >= 1e3 && ![
		"percent",
		"ms",
		"score",
		"ratio"
	].includes(t) && (i.notation = "compact", i.maximumFractionDigits = 1);
	let a = new Intl.NumberFormat(document.documentElement.lang || "en", i).format(e);
	return t === "percent" ? `${a}%` : t === "ms" ? `${a} ms` : t === "score" ? `${a}/100` : a;
}
function Rd(e, t = !1) {
	let n = /* @__PURE__ */ new Date(`${e}T12:00:00Z`);
	return Number.isNaN(n.getTime()) ? e : new Intl.DateTimeFormat(document.documentElement.lang || "en", {
		day: "numeric",
		month: "short",
		...t ? { year: "numeric" } : {},
		timeZone: "UTC"
	}).format(n);
}
function zd() {
	let e = Ad.get("numbers", "full") === "compact";
	document.querySelectorAll("[data-metric-number]").forEach((t) => {
		let n = Id(t.dataset.metricNumber);
		if (n === null) return;
		let r = Ld(n, t.dataset.unit, e);
		t.textContent !== r && (t.textContent = r), t.title = Ld(n, t.dataset.unit, !1);
	});
}
function Bd() {
	let e = getComputedStyle(Od);
	return Object.fromEntries([
		"chart-line",
		"chart-previous",
		"chart-grid",
		"text-muted",
		"page",
		"surface",
		"text",
		"event-blue",
		"event-green",
		"event-amber",
		"event-violet",
		"event-automatic"
	].map((t) => [t, e.getPropertyValue(`--${t}`).trim()]));
}
var Vd = {
	id: "metribayEvents",
	afterDatasetsDraw(e, t, n) {
		let r = n.events ?? [];
		if (!r.length) return;
		let { ctx: i, chartArea: { top: a, bottom: o }, scales: { x: s } } = e;
		i.save();
		for (let e of r) {
			let t = n.labels.indexOf(e.date);
			if (t < 0) continue;
			let r = s.getPixelForValue(t), c = n.colors[`event-${e.automatic ? "automatic" : e.color}`] || n.colors["event-blue"];
			i.strokeStyle = c, i.fillStyle = c, i.lineWidth = 1.4, i.setLineDash(e.automatic ? [2, 3] : [4, 3]), i.beginPath(), i.moveTo(r, a + 6), i.lineTo(r, o), i.stroke(), i.setLineDash([]), i.beginPath(), e.automatic ? (i.moveTo(r, a - 1), i.lineTo(r + 4, a + 3), i.lineTo(r, a + 7), i.lineTo(r - 4, a + 3), i.closePath()) : i.arc(r, a + 3, 3.5, 0, Math.PI * 2), i.fill();
		}
		i.restore();
	}
}, Hd = null;
function Ud(e, t) {
	if (e !== Hd) {
		Hd = e;
		for (let n of kd) n.metribayCrossDate = e, n !== t && n.draw();
	}
}
var Wd = {
	id: "metribayCrosshair",
	afterEvent(e, t) {
		let { event: n } = t;
		if (n.type === "mouseout" || !t.inChartArea) {
			Hd !== null && (Ud(null, e), t.changed = !0);
			return;
		}
		if (![
			"mousemove",
			"touchstart",
			"touchmove"
		].includes(n.type)) return;
		let r = Math.round(e.scales.x.getValueForPixel(n.x)), i = e.data.labels[Math.max(0, Math.min(e.data.labels.length - 1, r))] ?? null;
		i !== Hd && (Ud(i, e), t.changed = !0);
	},
	afterDatasetsDraw(e) {
		let t = Hd === null ? -1 : e.data.labels.indexOf(Hd);
		if (t < 0) return;
		let { ctx: n, chartArea: { top: r, bottom: i }, scales: { x: a, y: o } } = e, s = e.metribayColors, c = a.getPixelForValue(t);
		n.save(), n.strokeStyle = s["text-muted"], n.globalAlpha = .55, n.lineWidth = 1, n.beginPath(), n.moveTo(c, r), n.lineTo(c, i), n.stroke();
		let l = e.isDatasetVisible(0) ? Id(e.data.datasets[0].data[t]) : null;
		l !== null && !e.tooltip?.getActiveElements().length && (n.globalAlpha = 1, n.fillStyle = s["chart-line"], n.strokeStyle = s.surface, n.lineWidth = 2, n.beginPath(), n.arc(c, o.getPixelForValue(l), 3.5, 0, Math.PI * 2), n.fill(), n.stroke()), n.restore();
	}
};
function Gd(e, t, n) {
	let r = Bd(), i = [
		"l",
		"s",
		"n"
	].includes(t.size) ? t.size : "l", a = i === "l", o = i === "n", s = Array.isArray(t.series) ? t.series : [], c = !o && Array.isArray(t.previous) ? t.previous : [], l = s.map((e) => e.date), u = c.some((e) => Id(e.value) !== null), d = o || !Array.isArray(t.events) ? [] : t.events.filter((e) => l.includes(e.date)), f = (e) => d.filter((t) => t.date === e), p = (e, n) => {
		if (!t.eventsUrl || !d.length) return null;
		let r = null;
		for (let t of new Set(d.map((e) => e.date))) {
			let i = Math.abs(e.scales.x.getPixelForValue(l.indexOf(t)) - n.x);
			i <= 6 && (!r || i < r.distance) && (r = {
				date: t,
				distance: i
			});
		}
		return r?.date ?? null;
	}, m = (e) => {
		let n = f(e);
		n.length && t.eventsUrl && window.location.assign(`${t.eventsUrl}${t.eventsUrl.includes("?") ? "&" : "?"}date=${e}#${n.length === 1 ? n[0].anchor : `day-${e}`}`);
	}, h = (e) => Id(s[e]?.value) !== null, g = (e) => h(e) && !h(e - 1) && !h(e + 1), _ = s.length - 1, v = [{
		label: $("Selected period"),
		data: s.map((e) => Id(e.value)),
		borderColor: r["chart-line"],
		backgroundColor: `${r["chart-line"]}${o ? "14" : "0c"}`,
		borderWidth: a ? 2 : 1.6,
		pointRadius(e) {
			return e.dataIndex === _ && s[_]?.partial ? a ? 3 : 2.3 : g(e.dataIndex) ? 2 : 0;
		},
		pointHoverRadius: o ? 2.5 : 4,
		pointBorderWidth: 1.5,
		pointBackgroundColor(e) {
			return s[e.dataIndex]?.partial ? r.surface : r["chart-line"];
		},
		pointBorderColor: r["chart-line"],
		segment: { borderDash(e) {
			return s[e.p1DataIndex]?.partial ? [2, 2.5] : void 0;
		} },
		spanGaps: !1,
		tension: .18,
		fill: !0,
		stepped: t.unit === "status"
	}];
	u && v.push({
		label: $("Previous period"),
		data: l.map((e, t) => Id(c[t]?.value)),
		borderColor: r["chart-previous"],
		backgroundColor: "transparent",
		borderDash: [3, 3],
		borderWidth: 1.2,
		pointRadius: 0,
		pointHoverRadius: a ? 3 : 2,
		spanGaps: !1,
		tension: .18,
		fill: !1,
		stepped: t.unit === "status"
	});
	let y = e.closest("[data-chart-group]");
	for (let [e, t] of v.entries()) t.hidden = y?.querySelector(`[data-chart-toggle="${e}"]`)?.getAttribute("aria-pressed") === "false";
	let b = {
		family: "system-ui",
		size: 10
	}, x = new Dc(e, {
		type: "line",
		data: {
			labels: l,
			datasets: v
		},
		plugins: [Vd, Wd],
		options: {
			responsive: !0,
			maintainAspectRatio: !1,
			animation: !1,
			normalized: !0,
			interaction: {
				mode: "index",
				intersect: !1
			},
			onClick(e, t, n) {
				let r = p(n, e);
				r && m(r);
			},
			onHover(e, t, n) {
				n.canvas.style.cursor = p(n, e) ? "pointer" : "";
			},
			layout: { padding: {
				top: d.length ? 10 : 4,
				left: 0,
				right: o ? 3 : 2,
				bottom: o ? 2 : 0
			} },
			plugins: {
				legend: { display: !1 },
				metribayEvents: {
					events: d,
					labels: l,
					colors: r
				},
				tooltip: {
					enabled: !0,
					backgroundColor: r.text,
					titleColor: r.page,
					bodyColor: r.page,
					padding: o ? 8 : 10,
					displayColors: a,
					titleFont: {
						family: "system-ui",
						size: 11,
						weight: "normal"
					},
					bodyFont: {
						family: "system-ui",
						size: 11
					},
					callbacks: {
						title(e) {
							return e.length ? Rd(s[e[0].dataIndex]?.date ?? "", !0) : "";
						},
						label(e) {
							let n = (e.datasetIndex === 0 ? s : c)[e.dataIndex], r = Ld(Id(e.raw), t.unit, !1, t.currency);
							return `${e.datasetIndex === 0 ? t.label : $("Previous (:date)", { date: Rd(n?.date ?? "") })}: ${r}${n?.partial ? ` · ${$("partial")}` : ""}`;
						},
						afterBody(e) {
							if (!e.length) return [];
							let t = (e[0].datasetIndex === 0 ? s : c)[e[0].dataIndex];
							return [...t?.context?.collection_start ? [`${t.context.collection_start} – ${t.context.collection_end}`] : [], ...f(s[e[0].dataIndex]?.date).map((e) => `${e.automatic ? "◆" : "●"} ${e.title}`)];
						}
					}
				}
			},
			scales: {
				x: {
					display: !o,
					grid: {
						display: !1,
						drawTicks: !1
					},
					border: { display: !1 },
					ticks: a ? {
						color: r["text-muted"],
						maxTicksLimit: 5,
						maxRotation: 0,
						align: "inner",
						padding: 8,
						font: b,
						callback(e) {
							return Rd(l[e] ?? "");
						}
					} : {
						color: r["text-muted"],
						autoSkip: !1,
						maxRotation: 0,
						align: "inner",
						padding: 5,
						font: b,
						callback(e, t) {
							return t === 0 || t === _ ? Rd(l[e] ?? "") : "";
						}
					}
				},
				y: {
					display: !o,
					beginAtZero: !o && !["percent", "score"].includes(t.unit),
					suggestedMax: !o && ["percent", "score"].includes(t.unit) ? 100 : void 0,
					grid: {
						color: r["chart-grid"],
						drawTicks: !1
					},
					border: { display: !1 },
					ticks: a ? {
						color: r["text-muted"],
						maxTicksLimit: 4,
						stepSize: t.unit === "status" ? 1 : void 0,
						padding: 8,
						font: b,
						callback(e) {
							return Ld(e, t.unit, !0, t.currency);
						}
					} : {
						display: !1,
						maxTicksLimit: 3
					}
				}
			}
		}
	});
	return x.metribaySignature = n, x.metribayColors = r, x.metribayCrossDate = Hd, kd.add(x), o || (e.tabIndex = 0, e.setAttribute("aria-keyshortcuts", "ArrowLeft ArrowRight"), e.addEventListener("keydown", (n) => {
		if (![
			"ArrowLeft",
			"ArrowRight",
			"Escape",
			"Enter"
		].includes(n.key)) return;
		let r = Dc.getChart(e);
		if (r) {
			if (n.key === "Enter") {
				let e = s[r.metribayCursor]?.date;
				e && f(e).length && (n.preventDefault(), m(e));
			} else {
				if (n.preventDefault(), n.key === "Escape") r.setActiveElements([]), r.tooltip.setActiveElements([], {
					x: 0,
					y: 0
				});
				else {
					let i = n.key === "ArrowRight" ? 1 : -1, a = r.metribayCursor ?? r.getActiveElements()[0]?.index ?? (i > 0 ? -1 : s.length), o = Math.max(0, Math.min(s.length - 1, a + i)), l = (e) => r.data.datasets.some((t, n) => r.isDatasetVisible(n) && Id(t.data[e]) !== null);
					for (; !l(o) && o + i >= 0 && o + i < s.length;) o += i;
					r.metribayCursor = o;
					let d = e.closest(".metric")?.querySelector("[data-chart-announcement]");
					if (d) {
						let e = [];
						r.isDatasetVisible(0) && e.push(`${Rd(s[o]?.date ?? "", !0)}. ${t.label}: ${Ld(Id(s[o]?.value), t.unit, !1, t.currency)}${s[o]?.partial ? `. ${$("Partial date.")}` : ""}`), u && r.isDatasetVisible(1) && e.push(`${$("Previous period")} ${Rd(c[o]?.date ?? "", !0)}: ${Ld(Id(c[o]?.value), t.unit, !1, t.currency)}${c[o]?.partial ? `. ${$("Partial date.")}` : ""}`);
						for (let t of f(s[o]?.date)) e.push($("Event: :title", { title: t.title }));
						d.textContent = e.join(". ");
					}
					let p = r.data.datasets.map((e, t) => ({
						datasetIndex: t,
						index: o
					})).filter((e) => r.isDatasetVisible(e.datasetIndex) && Id(r.data.datasets[e.datasetIndex].data[o]) !== null);
					r.setActiveElements(p), r.tooltip.setActiveElements(p, {
						x: r.scales.x.getPixelForValue(o),
						y: r.chartArea.top
					});
				}
				r.update("none");
			}
		}
	}, { signal: Kd(x) })), x;
}
function Kd(e) {
	return e.metribayAbort = new AbortController(), e.metribayAbort.signal;
}
function qd(e) {
	e.metribayAbort?.abort(), e.destroy(), kd.delete(e);
}
function Jd() {
	for (let e of kd) e.canvas?.isConnected || qd(e);
	document.querySelectorAll("canvas[data-metric-chart]").forEach((e) => {
		if (e.closest("[data-group-body][hidden]")) return;
		let t = e.closest("[data-chart-container]").dataset.chartConfig, n = `${t}|${Od.dataset.theme}|${Od.dataset.density}`, r = Dc.getChart(e);
		if (r?.metribaySignature !== n) {
			r && qd(r);
			try {
				let r = JSON.parse(t);
				e.setAttribute("aria-label", r.ariaLabel), Gd(e, r, n);
			} catch (t) {
				console.error("A metric chart could not be rendered.", t);
				let n = document.createElement("p");
				n.className = "field-hint", n.textContent = $("The chart could not load. Daily values remain available below."), e.replaceWith(n);
			}
		}
	});
}
var Yd = /* @__PURE__ */ new Map();
function Xd(e) {
	let t = Yd.get(e.id) ?? {}, n = [...e.querySelectorAll("[data-query-source-panel]")], r = n.find((e) => e.dataset.querySourcePanel === t.source) ?? n[0];
	if (!r) return;
	let i = [...e.querySelectorAll("[data-query-view]")], a = i.some((e) => e.dataset.queryView === t.view) ? t.view : i.find((e) => e.getAttribute("aria-selected") === "true")?.dataset.queryView, o = (r.dataset.queryCounts || "").split(",");
	n.forEach((e) => {
		e.hidden = e !== r;
	}), e.querySelectorAll("[data-query-source]").forEach((e) => e.setAttribute("aria-pressed", String(e.dataset.querySource === r.dataset.querySourcePanel))), i.forEach((t, n) => {
		let i = t.dataset.queryView === a;
		t.setAttribute("aria-selected", String(i)), t.tabIndex = i ? 0 : -1, t.setAttribute("aria-controls", `${e.id}-${r.dataset.querySourcePanel}-${t.dataset.queryView}`);
		let s = t.querySelector("[data-query-count]");
		if (s && o[n] !== void 0) {
			let e = new Intl.NumberFormat(document.documentElement.lang || "en").format(Number(o[n]));
			s.textContent !== e && (s.textContent = e);
		}
	}), r.querySelectorAll("[data-query-panel]").forEach((e) => {
		e.hidden = e.dataset.queryPanel !== a;
		let n = (t.expanded ?? []).includes(e.id);
		e.querySelectorAll("[data-query-extra]").forEach((e) => {
			e.hidden = !n;
		});
		let r = e.querySelector("[data-query-more]");
		if (r) {
			r.setAttribute("aria-expanded", String(n));
			let e = n ? r.dataset.labelLess : r.dataset.labelMore;
			r.textContent !== e && (r.textContent = e);
		}
	});
}
function Zd() {
	document.querySelectorAll("[data-query-block]").forEach((e) => {
		Yd.has(e.id) && Xd(e);
	});
}
function Qd(e, t) {
	let n = {
		expanded: [],
		...Yd.get(e.id) ?? {}
	};
	t(n), Yd.set(e.id, n), Xd(e);
}
function $d() {
	let e = document.querySelector("[data-project-search]");
	if (!e) return;
	let t = e.value.trim().toLocaleLowerCase(), n = 0;
	document.querySelectorAll("[data-project-name]").forEach((e) => {
		e.hidden = !e.dataset.projectName.includes(t), e.hidden || n++;
	});
	let r = document.querySelector("[data-search-empty]");
	r && (r.hidden = !t || n > 0);
}
function ef() {
	let e = document.querySelector("[data-number-preference]"), t = document.querySelector("[data-density-preference]");
	e && (e.value = Ad.get("numbers", "full")), t && (t.value = Ad.get("density", "comfortable")), document.querySelectorAll("[data-theme-input]").forEach((e) => {
		e.checked = e.value === Pd();
	});
}
function tf() {
	Md || (Md = !0, requestAnimationFrame(() => {
		Md = !1, Ju(), Cd(), Jd(), zd(), Zd(), $d();
	}));
}
function nf(e, t = !1) {
	let n = e.querySelector("[data-provider-switch]")?.value;
	if (!n) return;
	e.querySelectorAll("[data-provider-panel]").forEach((e) => {
		e.hidden = e.dataset.providerPanel !== n, e.disabled = e.hidden;
	});
	let r = e.querySelector("[data-connection-select]");
	if (r) {
		let i = n === "wporg", a = r.querySelector("option[value=\"\"]");
		a && (a.textContent = $(i ? "No credentials needed" : "Choose a connected account")), r.querySelectorAll("[data-provider-kind]").forEach((e) => {
			e.hidden = e.dataset.providerKind !== n, e.disabled = e.hidden;
		}), (t || r.selectedOptions[0]?.disabled) && (r.value = ""), r.required = !i;
		let o = e.querySelector("[data-connection-hint]");
		o && (o.hidden = i);
	}
	let i = e.querySelector("[data-provider-timezone]"), a = [...e.querySelectorAll("[data-provider-panel]")].find((e) => e.dataset.providerPanel === n);
	i && t && a?.dataset.defaultTimezone && (i.value = a.dataset.defaultTimezone);
}
function rf() {
	let e = document.querySelector("[data-preference-status]");
	e && (e.textContent = $("Preference saved in this browser."));
}
function af() {
	let e = ju(window.location.hash);
	if (!e || e.tagName === "DIALOG") return;
	let t = e.closest("details");
	t && !t.open && (t.open = !0);
	let n = Yu(e);
	if (e.matches("[data-query-panel]")) {
		let t = e.closest("[data-query-block]"), n = e.closest("[data-query-source-panel]");
		t && n && Qd(t, (t) => {
			t.source = n.dataset.querySourcePanel, t.view = e.dataset.queryPanel;
		});
	}
	let r = () => {
		if (e.scrollIntoView({ block: "center" }), e.classList.add("is-target"), e.closest("[data-chart-group]")) {
			let t = (e.matches("h1, h2, h3") ? e : e.querySelector("h2, h3")) || e;
			!t.matches("a, button, input, select, textarea, summary") && !t.hasAttribute("tabindex") && (t.tabIndex = -1), t.focus({ preventScroll: !0 });
		}
		setTimeout(() => e.classList.remove("is-target"), 2400);
	};
	n || e.closest("[data-group-body]") ? requestAnimationFrame(() => requestAnimationFrame(r)) : r();
}
window.addEventListener("hashchange", af);
function of() {
	Fd(), ef(), Ju(), Dd(), document.querySelectorAll("dialog[data-open-on-load]").forEach((e) => {
		e.open || e.showModal();
	}), af(), document.querySelector("[data-validation-errors]")?.focus(), document.querySelectorAll("[data-provider-form]").forEach((e) => nf(e)), tf();
}
document.addEventListener("click", (e) => {
	let t = e.target.closest("[data-dialog-open]");
	t && document.getElementById(t.dataset.dialogOpen)?.showModal();
	let n = e.target.closest("[data-dialog-close]");
	if (n && n.closest("dialog")?.close(), e.target.closest("[data-theme-cycle]")) {
		let e = Pd();
		Fd(e === "system" ? Od.dataset.theme === "light" ? "dark" : "light" : {
			light: "dark",
			dark: "system"
		}[e], !0);
	}
	let r = e.target.closest("[data-chart-toggle]");
	if (r) {
		let e = Number(r.dataset.chartToggle), t = r.getAttribute("aria-pressed") === "false";
		r.setAttribute("aria-pressed", String(t)), r.closest("[data-chart-group]")?.querySelectorAll("canvas[data-metric-chart]").forEach((n) => {
			let r = Dc.getChart(n);
			r?.data.datasets[e] && (r.setDatasetVisibility(e, t), r.update("none"));
		});
	}
	let i = e.target.closest("[data-query-block]"), a = e.target.closest("[data-query-view]"), o = e.target.closest("[data-query-source]"), s = e.target.closest("[data-query-more]");
	if (i && a && Qd(i, (e) => {
		e.view = a.dataset.queryView;
	}), i && o && Qd(i, (e) => {
		e.source = o.dataset.querySource;
	}), i && s) {
		let e = s.closest("[data-query-panel]");
		Qd(i, (t) => {
			t.expanded = t.expanded.includes(e.id) ? t.expanded.filter((t) => t !== e.id) : [...t.expanded, e.id];
		}), s.getAttribute("aria-expanded") === "false" && s.scrollIntoView({ block: "nearest" });
	}
	let c = e.target.closest("[data-row-href]");
	c && !e.target.closest("a, button, input, select, label") && !window.getSelection()?.toString() && (e.metaKey || e.ctrlKey ? window.open(c.dataset.rowHref, "_blank", "noopener") : window.location.assign(c.dataset.rowHref));
	let l = e.target.closest("[data-two-factor-toggle]");
	if (l) {
		let e = l.closest("form"), t = e.querySelector("[data-recovery-field]"), n = e.querySelector("[data-authenticator-field]"), r = t.hidden;
		t.hidden = !r, n.hidden = r, t.querySelector("input").disabled = !r, n.querySelector("input").disabled = r, t.querySelector("input").required = r, n.querySelector("input").required = !r, l.textContent = $(r ? "Use an authenticator code" : "Use a recovery code"), (r ? t : n).querySelector("input").focus();
	}
	document.querySelectorAll(".account-menu[open], .range-picker[open], .view-menu[open]").forEach((t) => {
		t.contains(e.target) || (t.open = !1);
	});
}), document.addEventListener("change", (e) => {
	let t = e.target;
	t.matches("[data-theme-input]") && (Fd(t.value, !0), rf()), t.matches("[data-number-preference]") && (Ad.set("numbers", t.value), zd(), rf()), t.matches("[data-density-preference]") && (Ad.set("density", t.value), Od.dataset.density = t.value, tf(), rf()), t.matches("[data-provider-switch]") && nf(t.closest("form"), !0), t.matches("[data-auto-submit]") && t.form.requestSubmit();
}), document.addEventListener("input", (e) => {
	e.target.matches("[data-project-search]") && $d();
}), document.addEventListener("submit", (e) => {
	let t = e.target;
	if (t.dataset.confirm && !window.confirm(t.dataset.confirm)) {
		e.preventDefault();
		return;
	}
	let n = e.submitter;
	n && !t.hasAttribute("wire:submit") && (n.dataset.originalLabel = n.textContent, n.setAttribute("aria-busy", "true"), queueMicrotask(() => {
		e.defaultPrevented || (n.disabled = !0);
	}));
}), document.addEventListener("keydown", (e) => {
	let t = e.target.closest("[role=\"tab\"]");
	if (!t || ![
		"ArrowLeft",
		"ArrowRight",
		"Home",
		"End"
	].includes(e.key)) return;
	let n = [...t.closest("[role=\"tablist\"]").querySelectorAll("[role=\"tab\"]")], r = n.indexOf(t), i = n[{
		ArrowLeft: (r - 1 + n.length) % n.length,
		ArrowRight: (r + 1) % n.length,
		Home: 0,
		End: n.length - 1
	}[e.key]];
	e.preventDefault(), i.focus(), i.click();
}), document.addEventListener("keydown", (e) => {
	e.key === "Escape" && document.querySelectorAll(".account-menu[open], .range-picker[open], .view-menu[open]").forEach((e) => {
		e.open = !1, e.querySelector("summary")?.focus();
	});
}), document.addEventListener("focusin", (e) => {
	document.querySelectorAll(".account-menu[open], .range-picker[open], .view-menu[open]").forEach((t) => {
		t.contains(e.target) || (t.open = !1);
	});
}), window.addEventListener("pageshow", () => {
	document.querySelectorAll("button[aria-busy=\"true\"]").forEach((e) => {
		e.disabled = !1, e.removeAttribute("aria-busy");
	});
}), new MutationObserver(tf).observe(document.body, {
	childList: !0,
	subtree: !0,
	attributes: !0,
	attributeFilter: ["data-chart-config"]
}), document.addEventListener("livewire:navigated", of), document.addEventListener("livewire:init", () => {
	window.Livewire.hook("morphed", tf);
}), Qu(tf), of();
function sf() {
	document.querySelectorAll("[data-resource-search]").forEach((e) => {
		e.oninput = () => document.querySelectorAll("[data-resource-row]").forEach((t) => {
			t.hidden = !t.textContent.toLowerCase().includes(e.value.toLowerCase());
		});
	});
}
document.addEventListener("DOMContentLoaded", sf), document.addEventListener("livewire:navigated", sf), ku();
