import{_ as d}from"./index.js";import{b as n,c as t,i as a,g as p,j as o,n as r,F as u,M as w,e as x,f as _,t as c,x as f,l as g}from"./vendor.js";const B={class:"info-list-title"},b=n({props:{bold:{type:Boolean,default:!0},wide:{type:Boolean,default:!1}},setup(e){return(s,m)=>(t(),a("li",{class:r(["info-list-item",{wide:e.wide}])},[p("h2",B,[o(s.$slots,"title",{},void 0,!0)]),p("span",{class:r(["info-list-value",{bold:e.bold}])},[o(s.$slots,"default",{},void 0,!0)],2)],2))}});var h=d(b,[["__scopeId","data-v-902cf4dc"]]);const k=n({props:{items:{default:()=>[]},rowGap:{default:"l"}},setup(e){return(s,m)=>(t(),a("ul",{class:r(["info-list",`gap-${e.rowGap}`])},[o(s.$slots,"default",{},()=>[(t(!0),a(u,null,w(e.items,({title:i,content:l,bold:v,wide:y})=>(t(),a(u,{key:i+l},[l?(t(),x(h,{key:0,bold:v,wide:y},{title:_(()=>[c(f(i),1)]),default:_(()=>[c(f(l),1)]),_:2},1032,["bold","wide"])):g("",!0)],64))),128))],!0)],2))}});var $=d(k,[["__scopeId","data-v-1dd4d484"]]);export{$ as A,h as a};
