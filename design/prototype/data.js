// SYNTHETIC DEMONSTRATION DATA — fictional facilities, people and IDs. Not from KMHFL or any real system.
window.RF={};
RF.NOW=new Date('2026-03-18T09:40:00+03:00');
RF.facilities=[
{id:'f1',name:'Mto Mkuu Health Centre',type:'Health centre',keph:3,county:'Synthetic County A',code:'SYN-1001',hours:'Mon–Sat 07:30–17:00',phone:'+254 700 000 101',cap:['Antenatal','Immunisation','Basic lab','Referral out'],ver:{by:'S. Achieng (county coordinator)',at:'2026-03-16T10:00:00+03:00'},ambulance:false},
{id:'f2',name:'Ziwani Sub-County Hospital',type:'Sub-county hospital',keph:4,county:'Synthetic County A',code:'SYN-2001',hours:'24 hours',phone:'+254 700 000 102',cap:['Emergency','Maternity (comprehensive)','Basic surgery','X-ray','Lab','Referral in/out'],ver:{by:'J. Otieno (facility coordinator)',at:'2026-03-17T08:15:00+03:00'},ambulance:true},
{id:'f3',name:'Bahari County Referral Hospital',type:'County referral hospital',keph:5,county:'Synthetic County A',code:'SYN-3001',hours:'24 hours',phone:'+254 700 000 103',cap:['Emergency','ICU (4 beds)','CT scan','Paediatrics','Orthopaedics','Referral in'],ver:{by:'Dr. G. Mwangi (network manager)',at:'2026-03-12T14:30:00+03:00'},ambulance:true},
{id:'f4',name:'Kijani Dispensary',type:'Dispensary',keph:2,county:'Synthetic County A',code:'SYN-0901',hours:'Mon–Fri 08:00–16:30',phone:'+254 700 000 104',cap:['Outpatient','Immunisation','Referral out'],ver:{by:'S. Achieng (county coordinator)',at:'2026-03-05T09:00:00+03:00'},ambulance:false},
{id:'f5',name:'Mlima Mission Hospital',type:'Faith-based hospital',keph:4,county:'Synthetic County B',code:'SYN-2002',hours:'Mon–Sun 07:00–20:00',phone:'+254 700 000 105',cap:['Maternity (basic)','Eye clinic','Lab','Referral in'],ver:{by:'N. Kamau (facility coordinator)',at:'2026-02-02T11:00:00+03:00'},ambulance:false},
{id:'f6',name:'Pwani Eye & Specialist Centre',type:'Private specialist',keph:4,county:'Synthetic County B',code:'SYN-2003',hours:'Mon–Sat 08:00–17:00',phone:'+254 700 000 106',cap:['Ophthalmology','Cataract surgery','Referral in'],ver:{by:'unverified',at:null},ambulance:false},
{id:'f7',name:'Savanna Level 5 Hospital',type:'County referral hospital',keph:5,county:'Synthetic County B',code:'SYN-3002',hours:'24 hours',phone:'+254 700 000 107',cap:['Emergency','Maternity (comprehensive)','Dialysis','Referral in'],ver:{by:'P. Wafula (facility coordinator)',at:'2026-03-15T16:45:00+03:00'},ambulance:true},
{id:'f8',name:'Tumaini Community Clinic',type:'Clinic',keph:2,county:'Synthetic County B',code:'SYN-0902',hours:'Mon–Fri 09:00–17:00',phone:'+254 700 000 108',cap:['Outpatient','HIV care','Referral out'],ver:{by:'N. Kamau (facility coordinator)',at:'2026-01-20T09:30:00+03:00'},ambulance:false}];
RF.users=[
{id:'u1',name:'Amina Wekesa (synthetic)',role:'clinician',roleLabel:'Clinician',fac:'f1',mfa:true,last:'Today 07:52',status:'Active'},
{id:'u2',name:'Joseph Otieno (synthetic)',role:'coordinator',roleLabel:'Facility coordinator',fac:'f2',mfa:true,last:'Today 08:10',status:'Active'},
{id:'u3',name:'Dr. Grace Mwangi (synthetic)',role:'manager',roleLabel:'Network manager',fac:'f3',mfa:true,last:'Yesterday 17:20',status:'Active'},
{id:'u4',name:'Peter Njoroge (synthetic)',role:'admin',roleLabel:'Administrator',fac:'f3',mfa:true,last:'Today 06:30',status:'Active'},
{id:'u5',name:'Dr. Salim Hassan (synthetic)',role:'clinician',roleLabel:'Clinician',fac:'f3',mfa:true,last:'Today 08:44',status:'Active'},
{id:'u6',name:'Lucy Mutua (synthetic)',role:'clinician',roleLabel:'Clinician',fac:'f4',mfa:false,last:'Never',status:'Invited — MFA setup pending'}];
RF.STATUSES=['Created','Sent','Received','Accepted','Scheduled','Patient attended','Completed'];
RF.patients={
p1:{id:'SYN-P-0001',name:'Asha Mwende',sex:'F',age:27,note:'28 weeks gestation. BP 162/108 on two readings 15 min apart. Headache, blurred vision since morning. No seizures. Methyldopa 250 mg given.'},
p2:{id:'SYN-P-0002',name:'Baraka Otieno',sex:'M',age:6,note:'Fever 3 days, now drowsy. Temp 39.4, RDT positive. First dose artesunate given. Unable to take oral fluids.'},
p3:{id:'SYN-P-0003',name:'Halima Kariuki',sex:'F',age:64,note:'Progressive blurred vision both eyes, right worse. Visual acuity 6/60 R, 6/24 L. Suspected cataract.'},
p4:{id:'SYN-P-0004',name:'Kevin Mutiso',sex:'M',age:34,note:'Closed tibial fracture after fall, splinted. Neurovascular status intact. Analgesia given.'},
p5:{id:'SYN-P-0005',name:'Neema Chebet',sex:'F',age:41,note:'Creatinine raised on two tests. Needs nephrology review and dialysis assessment.'},
p6:{id:'SYN-P-0006',name:'Omondi Juma',sex:'M',age:9,note:'Recurrent wheeze, poorly controlled on salbutamol. Needs paediatric review.'},
p7:{id:'SYN-P-0007',name:'Zawadi Njeri',sex:'F',age:30,note:'Antenatal follow-up. Breech presentation at 36 weeks. Needs obstetric review and delivery plan.'},
p8:{id:'SYN-P-0008',name:'Ibrahim Salat',sex:'M',age:52,note:'Chest pain on exertion, ECG not available at this facility.'}};
const t=(d,h,m)=>`2026-03-${String(d).padStart(2,'0')}T${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:00+03:00`;
RF.referrals=[
{id:'r-7f3a',p:'p1',from:'f1',to:'f2',prio:'Emergency',reason:'Suspected severe pre-eclampsia, needs comprehensive obstetric care',status:'Sent',by:'u1',created:t(18,8,55),consent:'Verbal consent recorded by clinician',ev:[['Created',t(18,8,55),'A. Wekesa'],['Sent',t(18,9,2),'A. Wekesa']],msgs:[]},
{id:'r-2b91',p:'p2',from:'f4',to:'f3',prio:'Emergency',reason:'Severe malaria with altered consciousness, needs inpatient paediatric care',status:'Accepted',by:'u6',created:t(18,7,10),consent:'Guardian consent (verbal), witnessed',ev:[['Created',t(18,7,10),'L. Mutua'],['Sent',t(18,7,15),'L. Mutua'],['Received',t(18,7,31),'Bahari desk'],['Accepted',t(18,7,48),'Dr. S. Hassan']],msgs:[['Bahari desk','Bed held in paediatric ward. Send with IV access if possible.',t(18,7,50)],['Kijani Dispensary','Cannula in place. Ambulance requested.',t(18,7,58)]]},
{id:'r-c410',p:'p3',from:'f5',to:'f6',prio:'Routine',reason:'Cataract assessment and surgical planning',status:'Scheduled',by:'u1',created:t(11,10,0),consent:'Written consent on file',ev:[['Created',t(11,10,0),'N. Kamau'],['Sent',t(11,10,4),'N. Kamau'],['Received',t(12,8,30),'Pwani desk'],['Accepted',t(12,8,41),'Pwani desk'],['Scheduled',t(13,9,0),'Pwani desk']],appt:'2026-03-24T10:30',msgs:[]},
{id:'r-91de',p:'p4',from:'f2',to:'f3',prio:'Urgent',reason:'Closed tibial fracture, orthopaedic review',status:'Patient attended',by:'u2',created:t(16,13,5),consent:'Verbal consent recorded by clinician',ev:[['Created',t(16,13,5),'J. Otieno'],['Sent',t(16,13,8),'J. Otieno'],['Received',t(16,13,40),'Bahari desk'],['Accepted',t(16,14,2),'Bahari desk'],['Scheduled',t(16,14,3),'Bahari desk'],['Patient attended',t(17,10,20),'Bahari desk']],appt:'2026-03-17T10:00',msgs:[]},
{id:'r-5a07',p:'p5',from:'f2',to:'f7',prio:'Urgent',reason:'Renal function decline, dialysis assessment',status:'Completed',by:'u2',created:t(5,9,0),consent:'Written consent on file',ev:[['Created',t(5,9,0),'J. Otieno'],['Sent',t(5,9,3),'J. Otieno'],['Received',t(5,9,50),'Savanna desk'],['Accepted',t(5,10,20),'Savanna desk'],['Scheduled',t(5,10,22),'Savanna desk'],['Patient attended',t(6,9,40),'Savanna desk'],['Completed',t(9,15,0),'Dr. P. Wafula']],counter:'Dialysis assessment completed. Started on twice-weekly schedule. Return to Ziwani for monthly bloods; contact Savanna renal unit if oedema worsens.',msgs:[]},
{id:'r-e3c8',p:'p6',from:'f1',to:'f3',prio:'Routine',reason:'Poorly controlled asthma, paediatric review',status:'Declined',by:'u1',created:t(14,9,15),consent:'Guardian consent (written)',ev:[['Created',t(14,9,15),'A. Wekesa'],['Sent',t(14,9,18),'A. Wekesa'],['Received',t(14,11,0),'Bahari desk'],['Declined',t(14,11,30),'Bahari desk']],reasonCode:'Needs different level of care',reasonText:'Suitable for the sub-county hospital paediatric clinic. Suggest Ziwani.',msgs:[]},
{id:'r-0d6b',p:'p7',from:'f1',to:'f2',prio:'Urgent',reason:'Breech at 36 weeks, obstetric review and delivery plan',status:'Received',by:'u1',created:t(18,6,40),consent:'Written consent on file',ev:[['Created',t(18,6,40),'A. Wekesa'],['Sent',t(18,6,42),'A. Wekesa'],['Received',t(18,7,5),'Ziwani desk']],msgs:[]},
{id:'r-83aa',p:'p8',from:'f8',to:'f3',prio:'Urgent',reason:'Exertional chest pain, cardiology review and ECG',status:'Received',by:'u5',created:t(17,15,30),consent:'Verbal consent recorded by clinician',ev:[['Created',t(17,15,30),'N. Kamau'],['Sent',t(17,15,33),'N. Kamau'],['Received',t(17,16,2),'Bahari desk']],info:true,msgs:[['Bahari desk','Please send resting BP and any previous ECG. Is the patient on any cardiac medication?',t(18,8,10)]]},
{id:'r-b712',p:'p3',from:'f4',to:'f5',prio:'Routine',reason:'Eye review',status:'Cancelled',by:'u6',created:t(8,10,0),consent:'Verbal consent recorded by clinician',ev:[['Created',t(8,10,0),'L. Mutua'],['Sent',t(8,10,2),'L. Mutua'],['Cancelled',t(9,9,0),'L. Mutua']],reasonCode:'Patient chose to be treated elsewhere',reasonText:'',msgs:[]},
{id:'r-44f0',p:'p6',from:'f4',to:'f2',prio:'Routine',reason:'Paediatric review',status:'Patient attended',by:'u6',created:t(2,9,0),consent:'Guardian consent (written)',ev:[['Created',t(2,9,0),'L. Mutua'],['Sent',t(2,9,1),'L. Mutua'],['Received',t(2,9,40),'Ziwani desk'],['Accepted',t(2,10,0),'Ziwani desk'],['Scheduled',t(2,10,2),'Ziwani desk'],['Patient attended',t(4,11,0),'Ziwani desk']],stale:true,msgs:[]}];
RF.declineReasons=['No capacity at this time','Service not available at this facility','Needs different level of care','Information incomplete','Outside facility scope','Other (add note)'];
RF.cancelReasons=['Patient chose to be treated elsewhere','Patient condition changed','Referral created in error','Patient could not be reached','Transport or cost barrier','Other (add note)'];
RF.audit=[
['2026-03-18T09:35','Amina Wekesa','View','Referral r-7f3a','Masked view','—'],
['2026-03-18T09:20','Joseph Otieno','Reveal identifiers','Referral r-0d6b','Needed to confirm patient at desk','Logged'],
['2026-03-18T08:44','Dr. Salim Hassan','Break-glass','Referral r-2b91','Patient unresponsive, needed history, no desk access','Flagged for review'],
['2026-03-18T08:10','Joseph Otieno','Accept','Referral r-0d6b','—','—'],
['2026-03-18T07:52','Amina Wekesa','Login (MFA ok)','Session','—','—'],
['2026-03-17T16:02','Bahari desk','Message sent','Referral r-83aa','—','—'],
['2026-03-17T12:30','Peter Njoroge','Role changed','User u6','Clinician, MFA required','—'],
['2026-03-16T10:00','S. Achieng','Directory verified','Facility f1','—','—']];
RF.notifs=[
['A referral needs your response','Open ReferralFlow to view it.','09:02'],
['A message was added to a referral','Open ReferralFlow to read it.','08:10'],
['A referral was completed','A summary is ready to view.','Yesterday'],
['Directory entry is overdue for re-verification','One facility needs checking.','Yesterday']];
RF.weekly=[['W5',31],['W6',38],['W7',34],['W8',45],['W9',41],['W10',48]];
RF.facStats=[['Ziwani Sub-County Hospital',62,'3h 10m',9],['Bahari County Referral Hospital',58,'4h 40m',14],['Savanna Level 5 Hospital',31,'2h 55m',11],['Mlima Mission Hospital',12,'6h 20m',25],['Pwani Eye & Specialist Centre',4,'—',null]];
RF.reasonsInc=[['No capacity',9],['Service not available',7],['Patient did not attend',11],['Transport or cost',8],['Needs different level',4],['Other',3]];
