import{b as E,c as d,i as j,j as x,n as I,v as L,y as B,h as t,p as e,f as s,g as o,e as C,l as m,t as f,F as T,q as N,s as V}from"./vendor.js";import{P as h,a as y,D as F,S as g,_ as k}from"./SkillList.js";import{_ as v,u as D,b as p}from"./index.js";import{A as l,a as w}from"./AppList.js";import{_ as b}from"./OpenParticipationModalButton.js";const q=E({props:{cols:null},setup(i){function n(u){if(!u)return"";if(typeof u=="string")return u;let r="";for(let a=0;a<u;a++)r+="1fr ";return r}return(u,r)=>(d(),j("div",{class:I(["row",{mobile:!u.$props.cols||u.$props.cols===1}]),style:L({gridTemplateColumns:n(u.$props.cols)})},[x(u.$slots,"default",{},void 0,!0)],6))}});var A=v(q,[["__scopeId","data-v-71d0e132"]]);const M={key:0},z={class:"status-wrapper"},G=f(" \u041C\u0435\u0441\u0442 \u0432 \u043F\u0440\u043E\u0435\u043A\u0442\u0435 "),O=f(" \u0421\u0442\u0430\u0442\u0443\u0441 \u043F\u0440\u043E\u0435\u043A\u0442\u0430 "),R=f("\u0422\u0435\u0433\u0438"),H={class:"controls"},J=E({setup(i){const n=D(),{openedProject:u,loading:r,error:a}=B(n);return(P,$)=>t(u)&&!t(r)&&!t(a)?(d(),j("section",M,[e(p,null,{default:s(()=>[o("div",z,[o("div",null,[G,e(h,{class:"counter",total:t(u).places},null,8,["total"])]),o("div",null,[O,e(y,{class:"status",state:t(u).state},null,8,["state"])])]),e(A,null,{default:s(()=>{var c,_;return[e(l,{items:[{title:"\u0420\u0443\u043A\u043E\u0432\u043E\u0434\u0438\u0442\u0435\u043B\u0438 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:((c=t(u))==null?void 0:c.supervisors.length)?t(u).supervisors.join(", "):""},{title:"\u0421\u0442\u0430\u0440\u0442 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).date_start},{title:"\u0417\u0430\u043A\u0430\u0437\u0447\u0438\u043A",content:t(u).customer},{title:"\u0421\u043B\u043E\u0436\u043D\u043E\u0441\u0442\u044C",content:t(F)[t(u).difficulty]}]},null,8,["items"]),e(l,{items:[{title:"\u0422\u0440\u0435\u0431\u043E\u0432\u0430\u043D\u0438\u044F \u043A \u0441\u0442\u0443\u0434\u0435\u043D\u0442\u0430\u043C",content:t(u).requirements,wide:!0},{title:"\u0426\u0435\u043B\u044C \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).goal,wide:!0},{title:"\u041E\u043F\u0438\u0441\u0430\u043D\u0438\u0435 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).description,wide:!0}]},null,8,["items"]),e(l,{items:[{title:"\u041E\u0436\u0438\u0434\u0430\u0435\u043C\u044B\u0439 \u043F\u0440\u043E\u0434\u0443\u043A\u0442\u043E\u0432\u044B\u0439 \u0440\u0435\u0437\u0443\u043B\u044C\u0442\u0430\u0442",content:t(u).product_result,wide:!0},{title:"\u041E\u0436\u0438\u0434\u0430\u0435\u043C\u044B\u0439 \u0443\u0447\u0435\u0431\u043D\u044B\u0439 \u0440\u0435\u0437\u0443\u043B\u044C\u0442\u0430\u0442",content:t(u).study_result,wide:!0}]},null,8,["items"]),((_=t(u))==null?void 0:_.skills.length)>0?(d(),C(l,{key:0},{default:s(()=>[e(w,{bold:!1,wide:!0},{title:s(()=>[R]),default:s(()=>[e(g,{skills:t(u).skills,"show-all":""},null,8,["skills"])]),_:1})]),_:1})):m("",!0)]}),_:1}),o("div",H,[e(b,{project:t(u)},null,8,["project"]),e(k,{project:t(u)},null,8,["project"])])]),_:1})])):m("",!0)}});var K=v(J,[["__scopeId","data-v-6b7fb3a3"]]);const S=i=>(N("data-v-28bfa330"),i=i(),V(),i),Q={class:"desktop-details"},U=S(()=>o("h2",{class:"info-title"},"\u0421\u0442\u0430\u0442\u0443\u0441 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",-1)),W=S(()=>o("h2",{class:"info-title mt-4"},"\u041C\u0430\u043A\u0441\u0438\u043C\u0430\u043B\u044C\u043D\u043E\u0435 \u043A\u043E\u043B\u0438\u0447\u0435\u0441\u0442\u0432\u043E \u0441\u0442\u0443\u0434\u0435\u043D\u0442\u043E\u0432",-1)),X=f("\u0422\u0435\u0433\u0438"),Y=E({setup(i){const n=D(),{openedProject:u,loading:r,error:a}=B(n);return(P,$)=>{var c;return t(u)&&!t(r)&&!t(a)?(d(),j(T,{key:0},[o("section",Q,[e(p,null,{default:s(()=>[e(A,{cols:"4fr 4fr 2fr"},{default:s(()=>{var _;return[e(l,{items:[{title:"\u0420\u0443\u043A\u043E\u0432\u043E\u0434\u0438\u0442\u0435\u043B\u0438 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:((_=t(u))==null?void 0:_.supervisors.length)?t(u).supervisors.join(", "):""},{title:"\u0421\u0442\u0430\u0440\u0442 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).date_start},{title:"\u0421\u043B\u043E\u0436\u043D\u043E\u0441\u0442\u044C",content:t(F)[t(u).difficulty]},{title:"\u0417\u0430\u043A\u0430\u0437\u0447\u0438\u043A",content:t(u).customer}]},null,8,["items"]),e(l,{items:[{title:"\u0426\u0435\u043B\u044C \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).goal}]},null,8,["items"]),o("div",null,[U,e(y,{class:"badge mt-2",state:t(u).state},null,8,["state"]),W,e(h,{class:"mt-2",total:t(u).places},null,8,["total"]),e(b,{class:"mt-4",project:t(u)},null,8,["project"]),e(k,{class:"mt-4",project:t(u)},null,8,["project"])])]}),_:1})]),_:1}),e(p,null,{default:s(()=>[e(l,{items:[{title:"\u0422\u0440\u0435\u0431\u043E\u0432\u0430\u043D\u0438\u044F \u043A \u0441\u0442\u0443\u0434\u0435\u043D\u0442\u0430\u043C",content:t(u).requirements,wide:!0},{title:"\u041E\u043F\u0438\u0441\u0430\u043D\u0438\u0435 \u043F\u0440\u043E\u0435\u043A\u0442\u0430",content:t(u).description,wide:!0}]},null,8,["items"])]),_:1}),e(p,null,{default:s(()=>[e(A,{cols:2},{default:s(()=>[e(l,{items:[{title:"\u041E\u0436\u0438\u0434\u0430\u0435\u043C\u044B\u0439 \u043F\u0440\u043E\u0434\u0443\u043A\u0442\u043E\u0432\u044B\u0439 \u0440\u0435\u0437\u0443\u043B\u044C\u0442\u0430\u0442",content:t(u).product_result}]},null,8,["items"]),e(l,{items:[{title:"\u041E\u0436\u0438\u0434\u0430\u0435\u043C\u044B\u0439 \u0443\u0447\u0435\u0431\u043D\u044B\u0439 \u0440\u0435\u0437\u0443\u043B\u044C\u0442\u0430\u0442",content:t(u).study_result}]},null,8,["items"])]),_:1})]),_:1}),((c=t(u))==null?void 0:c.skills.length)>0?(d(),C(p,{key:0},{default:s(()=>[e(l,null,{default:s(()=>[e(w,{bold:!1,wide:!0},{title:s(()=>[X]),default:s(()=>[e(g,{skills:t(u).skills,"show-all":""},null,8,["skills"])]),_:1})]),_:1})]),_:1})):m("",!0)]),e(K,{class:"mobile-details"})],64)):m("",!0)}}});var l4=v(Y,[["__scopeId","data-v-28bfa330"]]);export{l4 as default};
