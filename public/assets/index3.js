import{b as v,r as d,Q as E,S as T,c as r,i as c,F as w,g as i,x as _,l as h,u as y,w as D,e as g,f as m,p as n,h as F,R as S,q as B,s as C}from"./vendor.js";import{_ as x,c as M,R as A}from"./index.js";import{S as I}from"./SidebarContainer.js";import{P as b,U as j}from"./PageLayout.js";import{a as l}from"./datetime.js";const k={class:"timer"},N={class:"title"},R={key:1,class:"title"},P=v({props:{deadline:null,timerText:null,afterTimerText:null},setup(e){const u=e,s=d(void 0),t=d(""),o=d(!1);function f(){const a=u.deadline.getTime()-Date.now();if(a<=0)return o.value=!0,p();t.value=l.fromObject({day:Math.floor(l.fromMillis(a).as("days")),hour:Math.floor(l.fromMillis(a).as("hours")),minutes:Math.floor(l.fromMillis(a).as("minutes"))}).toHuman()}function p(){window.clearInterval(s.value)}return E(()=>{f(),s.value=window.setInterval(f,1e3)}),T(p),(a,O)=>(r(),c("div",k,[o.value?h("",!0):(r(),c(w,{key:0},[i("div",N,_(t.value),1),i("div",null,_(u.timerText),1)],64)),o.value?(r(),c("div",R,_(u.afterTimerText),1)):h("",!0)]))}});var V=x(P,[["__scopeId","data-v-6c80d7a0"]]);const U=e=>(B("data-v-4017df3e"),e=e(),C(),e),H=U(()=>i("header",{class:"header"},[i("h1",{class:"title page-title"},"\u041F\u0440\u043E\u0444\u0438\u043B\u044C \u043F\u043E\u043B\u044C\u0437\u043E\u0432\u0430\u0442\u0435\u043B\u044F")],-1)),L=v({setup(e){const u=M(),s=y();return D(()=>u.isAuth,t=>{t||s.push({name:A.HOME})}),(t,o)=>(r(),g(b,null,{default:m(()=>[H,n(I,{class:"sidebar-container"},{sidebar:m(()=>[n(j),n(V,{deadline:new Date("2022/08/21 22:06:00"),"timer-text":"\u0438\u0434\u0451\u0442 \u0434\u043E\u0431\u043E\u0440 \u0437\u0430\u044F\u0432\u043E\u043A \u0432 \u043F\u0440\u043E\u0435\u043A\u0442\u044B","after-timer-text":"\u041F\u0440\u0438\u0435\u043C \u0437\u0430\u044F\u0432\u043E\u043A \u043D\u0430 \u043F\u0440\u043E\u0435\u043A\u0442\u043D\u043E\u0435 \u043E\u0431\u0443\u0447\u0435\u043D\u0438\u0435 \u0437\u0430\u043A\u043E\u043D\u0447\u0435\u043D"},null,8,["deadline"])]),main:m(()=>[n(F(S))]),_:1})]),_:1}))}});var J=x(L,[["__scopeId","data-v-4017df3e"]]);export{J as default};
