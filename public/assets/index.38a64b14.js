import{b as f,c as s,i as t,F as B,E,h as e,x as o,e as v,f as n,t as l,G as y,p as _,y as $,g as k,l as p,R as x}from"./vendor.af3187d6.js";import{_ as j,R as c,u as g,B as A}from"./index.bc2880d9.js";import{_ as P}from"./PageLayout.4ba74493.js";import"./BasePanel.da9733b3.js";const C=()=>window.history.length>2;const R={class:"breadcrumbs"},T=["onClick"],F={key:2},I=f({props:{breadcrumbs:null},setup(m){return(r,d)=>(s(),t("ul",R,[(s(!0),t(B,null,E(m.breadcrumbs,({to:u,title:a,back:b},i)=>(s(),t("li",{key:i,class:"breadcrumbs-item"},[b?(s(),t("button",{key:0,class:"breadcrumbs-link",onClick:h=>e(C)()?r.$router.back():r.$router.push({name:e(c).HOME})},o(a),9,T)):u?(s(),v(e(y),{key:1,to:u,class:"breadcrumbs-link"},{default:n(()=>[l(o(a),1)]),_:2},1032,["to"])):(s(),t("span",F,o(a),1))]))),128))]))}});var N=j(I,[["__scopeId","data-v-4e48e800"]]);const O=l(" \u043E \u043F\u0440\u043E\u0435\u043A\u0442\u0435 "),S=l(" \u0441\u043F\u0438\u0441\u043E\u043A \u0437\u0430\u044F\u0432\u043E\u043A "),V=f({setup(m){return(r,d)=>(s(),t("div",null,[_(e(y),{class:"project-tab",to:{name:e(c).PROJECT_DETAILS}},{default:n(()=>[O]),_:1},8,["to"]),_(e(y),{class:"project-tab",to:{name:e(c).PROJECT_PARTICIPATIONS}},{default:n(()=>[S]),_:1},8,["to"])]))}});var w=j(V,[["__scopeId","data-v-80a707ee"]]);const H={class:"header"},L={class:"page-title"},D={key:0},M={key:1},J={key:2},G={class:"d-flex justify-content-center mt-3"},q=l(" \u043D\u0430\u0437\u0430\u0434 \u043A \u0441\u043F\u0438\u0441\u043A\u0443 "),z=f({setup(m){const r=g(),{loading:d,error:u,openedProject:a}=$(r);return(b,i)=>(s(),v(P,null,{default:n(()=>{var h;return[k("header",H,[_(N,{class:"breadcrumbs",breadcrumbs:[{title:"\u0412\u0441\u0435 \u043F\u0440\u043E\u0435\u043A\u0442\u044B",to:{name:e(c).HOME}},{title:((h=e(a))==null?void 0:h.title)||""}]},null,8,["breadcrumbs"]),k("h1",L,[e(d)?(s(),t("span",D,"\u0437\u0430\u0433\u0440\u0443\u0437\u043A\u0430...")):p("",!0),e(u)?(s(),t("span",M,o(e(u)),1)):p("",!0),e(a)?(s(),t("span",J,o(e(a).title),1)):p("",!0)])]),!e(u)&&!e(d)?(s(),v(w,{key:0})):p("",!0),_(e(x)),k("div",G,[_(A,{case:"uppercase",variant:"link",onClick:i[0]||(i[0]=K=>b.$router.push({name:e(c).HOME}))},{default:n(()=>[q]),_:1})])]}),_:1}))}});var Y=j(z,[["__scopeId","data-v-c1f2e268"]]);export{Y as default};