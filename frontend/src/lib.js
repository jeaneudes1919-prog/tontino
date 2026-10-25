import {useCallback,useEffect,useState} from "react";import {errMsg} from "./api.js";
export const fcfa=n=>new Intl.NumberFormat("fr-FR").format(Math.round(n||0))+" FCFA";
export const num=n=>new Intl.NumberFormat("fr-FR").format(Math.round(n||0));
export const dateFr=d=>d?new Date(d).toLocaleDateString("fr-FR",{day:"numeric",month:"short",year:"numeric"}):"—";
export const dateTime=d=>d?new Date(d).toLocaleString("fr-FR",{day:"numeric",month:"short",hour:"2-digit",minute:"2-digit"}):"—";
export const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const B={green:"bg-emerald-400/15 text-emerald-300",amber:"bg-amber-400/15 text-amber-300",red:"bg-rose-400/15 text-rose-300",blue:"bg-sky-400/15 text-sky-300",gold:"bg-gold/15 text-gold2",gray:"bg-white/10 text-mist"};
export const STATUS={
contribution:{pending:["En attente",B.amber],paid:["Payée",B.green],late:["En retard",B.red],waived:["Remise",B.gray]},
loan:{requested:["Demandé",B.amber],approved:["Approuvé",B.blue],rejected:["Refusé",B.red],disbursed:["En cours",B.gold],repaid:["Soldé",B.green],defaulted:["En défaut",B.red]},
tontine:{draft:["En préparation",B.amber],active:["Active",B.green],completed:["Terminée",B.gray],cancelled:["Annulée",B.red]},
cycle:{upcoming:["À venir",B.gray],open:["En cours",B.amber],ready:["Prêt",B.green],paid_out:["Versé",B.gold]},
installment:{pending:["À venir",B.gray],partial:["Partiel",B.amber],late:["En retard",B.red],paid:["Payée",B.green]},
guarantor:{pending:["En attente",B.amber],accepted:["Accepté",B.green],declined:["Refusé",B.red]},
role:{admin:["Administrateur",B.gold],treasurer:["Trésorier",B.blue],member:["Membre",B.gray]}};
export const LEDGER={deposit:"Dépôt",withdrawal:"Retrait",contribution:"Cotisation",payout:"Cagnotte reçue",loan_disbursement:"Prêt reçu",loan_repayment:"Remboursement",interest:"Intérêts",penalty:"Pénalité",adjustment:"Ajustement"};
export function useLoad(fn,deps=[]){const [d,setD]=useState(null);const [err,setErr]=useState(null);
const reload=useCallback(()=>fn().then(x=>{setD(x);setErr(null)}).catch(e=>setErr(errMsg(e))),deps);
useEffect(()=>{setD(null);reload()},[reload]);return [d,reload,err]}
