CREATE COLUMN TABLE IMIP_ERESV.RESV_H (
    "DocNum" INTEGER null,
    "DocDate" DATE null,
    "RequiredDate" DATE null,
    "Requester" INTEGER null,
    "Division" VARCHAR (200) null,
    "Department" VARCHAR (200) null,
    "Company" VARCHAR (200) null,
    "Memo" NVARCHAR (5000) null,
    "Canceled" VARCHAR (20) null,
    "DocStatus" VARCHAR (20) null,
    "ApprovalStatus" VARCHAR (20) null,
    "ApprovalKey" INTEGER null,
    "isConfirmed" VARCHAR (20) null,
    "ConfirmDate" DATE null,
    "ConfirmBy" INTEGER null,
    "SAP_GIRNo" INTEGER null,
    "SAP_TrfNo" INTEGER null,
    "SAP_PRNo" INTEGER null,
    "CreateDate" DATE null,
    "CreateTime" TIME null,
    "CreatedBy" INTEGER null,
    "UpdateDate" DATE null,
    "UpdateTime" TIME null,
    "UpdatedBy" INTEGER null,
    "U_DocEntry" BIGINT not null,
    "RequestType" VARCHAR (50) null,
    U_NIK VARCHAR (30) null,
    "WhsCode" VARCHAR (20) null,
    "WhTo" VARCHAR (20) null,
    "Token" VARCHAR (200) null,
    "CreatedName" VARCHAR (200) null,
    "RequesterName" VARCHAR (200) null,
    "UrgentReason" VARCHAR (200) null,
    "ItemType" VARCHAR (20) null,
    PRIMARY KEY ("U_DocEntry")
    );


CREATE COLUMN TABLE IMIP_ERESV.RESV_D (
    "U_DocEntry" BIGINT null,
    "LineNum" INTEGER null,
    "ItemCode" VARCHAR (20) null,
    "ItemName" NVARCHAR (5000) null,
    "WhsCode" VARCHAR (20) null,
    "UoMCode" VARCHAR (20) null,
    "UoMName" VARCHAR (20) null,
    "ReqQty" DOUBLE null,
    "ReqDate" DATE null,
    "ReqNotes" NVARCHAR (5000) null,
    "OtherResvNo" VARCHAR (100) null,
    "RequestType" VARCHAR (20) null,
    "QtyReadyIssue" DOUBLE null,
    "LineStatus" VARCHAR (20) null,
    "SAP_GIRNo" INTEGER null,
    "SAP_TrfNo" INTEGER null,
    "SAP_PRNo" INTEGER null,
    "LineEntry" BIGINT not null,
    "ItemCategory" VARCHAR (30) null,
    "OIGRDocNum" BIGINT null,
    "InvntItem" VARCHAR (20) default 'Y' null,
    PRIMARY KEY ("LineEntry")
    );

CREATE COLUMN TABLE IMIP_ERESV.U_OITM (
    "U_Description" NVARCHAR (254) null,
    "U_UoM" NVARCHAR (20) null,
    "U_Status" NVARCHAR (20) null,
    "U_Remarks" NVARCHAR (254) null,
    "U_Supporting" NVARCHAR (254) null,
    "U_CreatedBy" INTEGER null,
    "U_DocEntry" BIGINT not null,
    "U_Comments" VARCHAR (200) null,
    "U_CreatedAt" TIMESTAMP default CURRENT_TIMESTAMP null,
    PRIMARY KEY ("U_DocEntry")
    );


select IMIP_ERESV."RESV_H".*,
       (SELECT STRING_AGG(X."PR_NO", ', ')
        FROM (SELECT DISTINCT Q0."DocNum" AS "PR_NO"
              FROM IMIP_TEST_1217."OPRQ" Q0
                       LEFT JOIN IMIP_TEST_1217."PRQ1" Q1 ON Q0."DocEntry" = Q1."DocEntry"
              WHERE Q1."U_DGN_IReqId" = RESV_H."SAP_GIRNo"
                AND Q0."CANCELED" = 'N') AS X)                             AS "SAP_PRNo",
       (SELECT "U_DocNum"
        FROM IMIP_TEST_1217."@DGN_EI_OIGR"
        where IMIP_TEST_1217."@DGN_EI_OIGR"."DocNum" = RESV_H."SAP_GIRNo") AS "SAP_GIRNo",
       (SELECT STRING_AGG(X."DocNum", ', ') as "GI_No"
        FROM (SELECT DISTINCT T0."DocNum"
              FROM IMIP_TEST_1217."@DGN_EI_OIGR" G0
                       LEFT JOIN IMIP_TEST_1217."@DGN_EI_IGR1" G1 ON G0."DocEntry" = G1."DocEntry"
                       LEFT JOIN IMIP_TEST_1217.IGE1 T1
                                 ON T1."U_DGN_IReqId" = G1."DocEntry" AND T1."U_DGN_IReqLineId" = G1."LineId"
                       LEFT JOIN IMIP_TEST_1217.OIGE T0 ON T1."DocEntry" = T0."DocEntry"
              WHERE G0."DocEntry" = RESV_H."SAP_GIRNo") AS X)              AS "SAP_GINo",
       IMIP_ERESV."RESV_H"."Company"                                       as "U_DbCode",
       (SELECT STRING_AGG(X."PONum", ', ') AS "PONum"
        FROM (SELECT DISTINCT T1."DocNum" AS "PONum"
              FROM IMIP_TEST_1217."POR1" AS T0
                       LEFT JOIN IMIP_TEST_1217."OPOR" AS T1 ON T0."DocEntry" = T1."DocEntry"
              WHERE T0."U_DGN_IReqId" = RESV_H."SAP_GIRNo"
                AND T1."CANCELED" = 'N') AS X)                             AS "PONum",
       (SELECT STRING_AGG(X."GRPO_NO", ', ') AS "GRPO_NO"
        FROM (SELECT DISTINCT T1."DocNum" AS "GRPO_NO"
              FROM IMIP_TEST_1217."PDN1" AS T0
                       LEFT JOIN IMIP_TEST_1217."OPDN" AS T1 ON T0."DocEntry" = T1."DocEntry"
              WHERE T0."U_DGN_IReqId" = RESV_H."SAP_GIRNo"
                AND T1."CANCELED" = 'N') AS X)                             AS "GRPONum",
       (SELECT STRING_AGG(X."TrfNo", ', ') AS "SAP_TrfNo"
        FROM (SELECT DISTINCT T2."DocNum" AS "TrfNo"
              FROM."RESV_H" T0
              LEFT JOIN IMIP_TEST_1217."@DGN_EI_IGR1" G1 ON T0."SAP_GIRNo" = G1."DocEntry"
                  LEFT JOIN IMIP_TEST_1217."@DGN_EI_OIGR" G0 ON G0."DocEntry" = G1."DocEntry"
                  LEFT JOIN IMIP_TEST_1217."WTR1" T1 ON G1."DocEntry" = T1."U_DGN_IReqId" AND T1."U_DGN_IReqLineId" = G1."LineId"
                  LEFT JOIN IMIP_TEST_1217."OWTR" T2 ON T1."DocEntry" = T2."DocEntry"
              WHERE T0."U_DocEntry" =.RESV_H."U_DocEntry") X)              AS "SAP_TrfNo",
       RESV_H."RequesterName"                                              AS "RequestName",
       CASE
           WHEN RESV_H."DocStatus" = 'D' THEN 'Draft'
           ELSE (SELECT CASE when G0."Status" = 'O' THEN 'Open' ELSE 'Closed' END AS "GIR_status"
                 FROM IMIP_TEST_1217."@DGN_EI_OIGR" G0
                 WHERE G0."DocEntry" = RESV_H."SAP_GIRNo") --WHEN RESV_H."DocStatus" = 'O' THEN 'Open' --WHEN RESV_H."DocStatus" = 'C' THEN 'Closed'
           END                                                             AS "DocumentStatus",
       CASE
           WHEN RESV_H."ApprovalStatus" = 'W' THEN 'Waiting'
           WHEN RESV_H."ApprovalStatus" = 'P' THEN 'Pending'
           WHEN RESV_H."ApprovalStatus" = 'N' THEN 'Rejected'
           WHEN RESV_H."ApprovalStatus" = 'Y' THEN 'Approved'
           WHEN RESV_H."ApprovalStatus" = '-' THEN '-' END                 AS "AppStatus",
       'action'                                                            AS "Action"
from IMIP_ERESV."RESV_H"
where "RESV_H"."ApprovalStatus" LIKE '%%'
  and IMIP_ERESV."RESV_H"."CreatedBy" = 88101989
order by IMIP_ERESV."RESV_H"."DocNum" desc limit 20
offset 0



