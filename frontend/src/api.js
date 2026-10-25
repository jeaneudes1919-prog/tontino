import axios from "axios";
const http=axios.create({baseURL:"/api/v1"});
http.interceptors.request.use(c=>{const t=localStorage.getItem("tontino_token");if(t)c.headers.Authorization="Bearer "+t;c.headers.Accept="application/json";return c});
http.interceptors.response.use(r=>r,e=>{if(e.response?.status===401&&!e.config.url.startsWith("/auth/")){localStorage.removeItem("tontino_token");window.dispatchEvent(new Event("tontino:logout"))}return Promise.reject(e)});
export const errMsg=e=>e.response?.data?.message&&!e.response?.data?.errors?e.response.data.message:Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||"Une erreur est survenue. Vérifiez votre connexion.";
export const api={get:(u,params)=>http.get(u,{params}).then(r=>r.data),post:(u,b)=>http.post(u,b).then(r=>r.data),put:(u,b)=>http.put(u,b).then(r=>r.data),patch:(u,b)=>http.patch(u,b).then(r=>r.data),del:u=>http.delete(u).then(r=>r.data)};
export async function download(url,name,params){const r=await http.get(url,{params,responseType:"blob"});const a=document.createElement("a");a.href=URL.createObjectURL(r.data);a.download=name;document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(a.href)}
