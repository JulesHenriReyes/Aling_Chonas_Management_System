# Milestone 3 upd.docx

[M3-P002] Order Management System with Sales Tracking and Report Generation for Aling Chona Cakes and Cupcakes

[M3-P008] In Partial fulfillment of the requirements in IT12/L

[M3-P009] System Integration and Architecture

[M3-P014] Presented By:

[M3-P016] Jan Rey P. Godelosao

[M3-P017] Jules Henri A. Reyes

[M3-P018] Blyte P. Hinay

[M3-P020] IT 12/L - 3459

[M3-P025] Presented To:

[M3-P027] CHARISSE P. BARBOSA

[M3-P028] Instructor

[M3-P032] September, 2026

[M3-P033] TABLE OF CONTENTS

[M3-P035] Chapter 1: The Company

[M3-P037] Company Profile

[M3-P038] 4

[M3-P039] Organizational Chart

[M3-P040] 4

[M3-P041] Business Environment

[M3-P042] 5

[M3-P043] Description of the Existing System

[M3-P044] 6

[M3-P045] Data Flow Diagram

[M3-P046] 7

[M3-P047] Chapter 2: The Problem

[M3-P049] Statement of the Problem

[M3-P050] 9

[M3-P051] Feasibility Study

[M3-P052] 10

[M3-P053] Chapter 3: The Solution

[M3-P055] Methodology

[M3-P056] (insert number here)

[M3-P057] System Objectives

[M3-P058] (insert number here)

[M3-P059] System Requirements

[M3-P060] (insert number here)

[M3-P061] Entity-Relationship Diagram

[M3-P062] (insert number here)

[M3-P063] Use Case Diagram

[M3-P064] (insert number here)

[M3-P065] Scopes and Limitations

[M3-P066] (insert number here)

[M3-P067] Security Plan

[M3-P068] (insert number here)

[M3-P069] Implementation Plan

[M3-P070] (insert number here)

[M3-P071] System Evaluation

[M3-P072] (insert number here)

[M3-P073] System Prototypes

[M3-P074] (insert number here)

[M3-P076] LIST OF TABLES

[M3-P078] Table 1: Operational Feasibility – PIECES Evaluation Framework

[M3-P079] 10

[M3-P080] Table 2: Technical Components and Approach

[M3-P081] 11

[M3-P082] Table 3:  Project Phases

[M3-P083] 13

[M3-P085] LIST OF FIGURES

[M3-P087] Figure 1: Organizational Chart

[M3-P088] 4

[M3-P089] Figure 2: Context Diagram

[M3-P090] 8

[M3-P091] Figure 3: Level 0 Diagram

[M3-P092] 8

[M3-P098] Chapter 1

[M3-P099] THE COMPANY

[M3-P101] Company Profile

[M3-P102] Aling Chona Cake & Cupcake is a local cake and cupcake business founded on December 30, 2016, by its owner, Chona C. Hinay. The business originally started at the owner's house and has since provided cakes and cupcakes for various occasions and customer needs. It offers customized orders, allowing customers to request specific designs, themes, and other preferences for their cakes. Currently, Aling Chona Cake & Cupcakes accepts orders through walk-in transactions and Facebook Messenger, where customers can communicate their order details and customization requests. For confirmed orders, customers are required to provide a 50% down payment, while the order information is recorded manually using a notebook or order form. Through these operations, Aling Chona Cake & Cupcake continues to serve customers by providing cakes and cupcakes according to their requested designs and schedules.

[M3-P104] Organizational Chart

[M3-P105] Because it is a locally owned business and small in size, Aling Chona Cake and Cupcakes has a direct organizational structure.

[M3-P107] Figure 1: Organizational Chart

[M3-P109] Business Environment

[M3-P110] Aling Chona Cake and Cupcakes is a micro-enterprise owned and run by its head baker and owner, Chona C. Hinay, which opened on December 30, 2016. The business is in the local food service and confectionery industry, and provides special celebration cakes and cupcakes for birthdays, weddings, anniversaries, graduations, baptisms, and other community events. It relies on individual customer service, custom-made manufacturing, and prompt order and payment processing. The business environment is the internal conditions that impact the day to day operations of the business and external conditions that impact the business's customers, market, suppliers, and business activities.

[M3-P112] Internal Business Environment

[M3-P113] Aling Chona Cake and Cupcakes has an organizational structure with only 2 staff members. Owner and head baker, and her daughter serves as her assistant and a baker. The owner is responsible for business administration and financial decisions. Both the owner and her assistant do the customer consultations, pricing, order scheduling, baking, and cake decoration. Her assistant helps with kitchen preparation, restocking supplies, order handovers, and records completed transactions. The two personnel communicate and coordinate manually on a daily basis.

[M3-P115] The business is a make to order operation. All orders must be placed in advance, and a 50% deposit must be paid at confirmation. The balance is collected on claim of the order. This payment method can minimize losses from cancelled orders, unused ingredients, and production costs. Because of storage space and the perishability of baking materials, ingredients are bought weekly or as required, instead of stocking up in large quantities.

[M3-P117] Order and financial administration is still largely manual. There is no point-of-sale system, computer inventory system, spreadsheet system, or accounting software used by the business. Customer orders, cake specifications are in the Facebook Messenger conversation, while payment details and completed transactions are recorded in a logbook. The transaction records are not always detailed enough to give information on sales, as detailed prices, expenses, or profit calculations are not always included. There is also no formal inventory record for supply purchases, which can make it difficult to track materials and related costs.

[M3-P119] External Business Environment

[M3-P120] The company’s main clients are local families, community groups, and individuals looking for customized cakes for special events at an affordable price. The primary way of communicating with customers is through Facebook and Messenger. Customers ask questions about the product if it’s their first time purchasing. Then they give references for cake designs, talk about themes, what kind of cake, get quotations and arrange for the pick up of the order. Its local competition is with other commercial bakeshops like Goldilocks and Red Ribbon, local home bakers, bakeries, and cake decorators. Commercial bakeshops have standardized products and computerized transaction systems, and local bakeshops may offer services that are comparable to Aling Chona Cake and Cupcakes.

[M3-P122] The business obtained its materials from local baking supply stores, wet markets, and supermarkets. They do not have formal supplier contracts therefore, any changes in retail prices will directly affect operating costs. Digital payment services are also used for down payments and order settlements by customers, especially GCash.

[M3-P125] Description of the Existing System

[M3-P126] The Existing system of Aling Chona Cake and Cupcakes relies on Facebook Messenger, GCash, and cash payments, handwritten logs, physical receipts, and manual calculations. The owner and assistant manage customer enquiries, order confirmations, payment, and baking. Order information is maintained through separate handwritten records.

[M3-P128] Customer Order Inquiry

[M3-P129] The process begins when a customer contacts the owner or her assistant through Facebook Messenger or visits the home bakery. For customized orders, the customer provides details such as color, tier/layers, theme, design, reference image if needed and, add ons. The owner then calculates the price based on the specifications and provides the total payable amount to the customer. The customer then confirms whether to proceed with the order.

[M3-P130] Down Payment Verification and Manual Order Recording

[M3-P131] A 50% down payment is required to confirm the order. Payment is made through GCash or Cash. The owner verifies GCash payments through the GCash application. After payment verification, the owner provides a physical receipt or if the payment is done online the receipt is captured and sent to the customer via Messenger. The owner then records the order in a physical logbook, which contains the customer's name and pickup date. Once the order is completed, the customer is notified through Messenger. Upon pickup, the customer pays the remaining balance through GCash or cash. Once the balance is settled, the order is considered completed and then recorded in the logbook.

[M3-P133] Procurement of Ingredients, Inventory tracking and baking

[M3-P134] The business follows a make to order process. The owner and assistant prepare the required ingredients and materials. During production, the owner refers to the recorded order details and design reference as a guide for preparation and decoration. The owner conducts its inventory tracking through observation and personal assessment to determine which and how many supplies need restocking.

[M3-P136] Data Flow Diagram

[M3-P138] Context Diagram - Aling Chona Cakes and Cupcake

[M3-P139] The customer provides the system with the order details containing the selected products, quantities, customization, and preferred payment method. After the order has been processed, the system sends the customer an order confirmation and physical receipt. The owner or assistant provides the system with a list of packages offered, which helps ensure that customers can only order products that are available. The system also provides the owner or assistant with the customer order and transaction log, allowing them to monitor orders, review payment transactions, and maintain accurate business records.

[M3-P141] Figure 2: Context Diagram

[M3-P143] Level 0 Diagram - Aling Chona Cakes and Cupcakes

[M3-P145] Figure 3: Level 0 Diagram

[M3-P151] Chapter 2

[M3-P152] THE PROBLEM

[M3-P154] Statement of the Problem

[M3-P155] After our initial interview with the business owner, we gathered information about a couple of problems that are affecting their operations. We identified several areas where the current business processes are inefficient, with order tracking being a primary concern.

[M3-P157] Specific Problem 1: Inefficient and Decentralized Order Tracking

[M3-P158] Orders and customization requests are taken on-site and/or through Facebook Messenger by either the owner or the assistant using their personal accounts, depending on who the customer contacts, resulting in order information being spread across chat conversations and handwritten records. The Cake and order specifications are manually recorded and verified by the owner and her assistant. Without a centralized order tracking system, there is a greater chance of losing information, communication delays, and order mistakes.

[M3-P160] Specific Problem 2: Lack of Sales and Expense Report

[M3-P161] Along with the lack of a centralized system, they also do not have any form of detailed sales and expense report. This makes it unclear for the owner to determine whether their products are generating income and make informed financial decisions. Additionally, manual checking of sales and expenses is time consuming and prone to calculation errors. In the interview, the owner also said that it would be nice if they could see the breakdown of their sales and expense report to have a better understanding of their flow of income.

[M3-P163] Specific Problem 3: Improper Inventory Tracking

[M3-P164] Aling Chona Cakes and Cupcakes also don't have proper inventory tracking. This has been a problem in cases where the ingredients ran out during production because the owner didn't correctly anticipate the amount of supplies and ingredients needed due to not having clear tracking of their supplies, and it has caused delays during production.

[M3-P167] Feasibility Study

[M3-P169] Operational Feasibility

[M3-P170] The two intended users, the Owner/Head Baker and the Assistant, already use their smartphones, Facebook Messenger, and GCash for daily operations, so the proposed system does not require them to learn a new way of working. At the initial interview, the owner didn't voice any concerns about using a computer based system and said automated record keeping would be beneficial to the business. The team will offer basic operational training and a brief orientation manual for order entry, payment logging, and report generation to assist with the transition.

[M3-P172] Table 1: Operational Feasibility – PIECES Evaluation Framework

[M3-P173] PIECES Category

[M3-P174] Current Operational State

[M3-P175] Proposed System Improvement

[M3-P176] Performance

[M3-P177] Staff manually search Messenger and notebook records for order and schedule information.

[M3-P178] Centralizes orders with search and filter by customer name, order ID, and pickup date.

[M3-P179] Information

[M3-P180] Order, payment, and sales details are scattered across chats and paper logs.

[M3-P181] Stores orders, customers, payments, and expense reports in one structured database.

[M3-P182] Economics

[M3-P183] Ingredient and supply expenses are not systematically documented.

[M3-P184] Records supply expenditures and surfaces organized financial information for decisions.

[M3-P185] Control

[M3-P186] Paper records are prone to wear, loss, and damage, with no access control.

[M3-P187] Adds user authentication, role-based access, and data validation.

[M3-P188] Efficiency

[M3-P189] Staff re-copy information from Messenger to notebooks and tally sales by hand.

[M3-P190] Reduces duplicate entry with automated order totals, balances, and sales summaries.

[M3-P191] Service

[M3-P192] Orders, payments, and pickup schedules are tracked manually.

[M3-P193] Organizes order, payment, production, and fulfillment data for more consistent service.

[M3-P195] Given this alignment with the staff's existing habits, the owner's stated willingness to adopt the tool, and a manageable training plan, Operational Feasibility is satisfied.

[M3-P197] Technical Feasibility

[M3-P199] The proposed system will be a web based application that will run on the business's existing personal computers and mobile devices, without requiring any additional servers, POS terminals, or barcode scanners.

[M3-P201] Table 2: Technical Components and Approach

[M3-P202] Component

[M3-P203] Approach

[M3-P204] Purpose

[M3-P205] Platform

[M3-P206] Web-based, accessible via standard browsers

[M3-P207] Runs on existing devices; no specialized hardware needed

[M3-P208] Backend Framework

[M3-P209] Laravel (PHP)

[M3-P210] Handles routing, authentication, sessions, and database interaction

[M3-P211] Database

[M3-P212] MySQL

[M3-P213] Structured storage for order, customer, payment, and expense records

[M3-P214] Frontend

[M3-P215] HTML, CSS

[M3-P216] Client-side interface

[M3-P217] Security

[M3-P218] Password hashing, authentication, input validation, role-based access

[M3-P219] Protects order, payment, and customer data

[M3-P220] Network Dependency

[M3-P221] Cloud-hosted; requires internet access

[M3-P222] During outages, staff record essential details manually and enter them once connectivity returns

[M3-P224] The development team’s expertise in these stacks is based on I.T.S Databases,  I.T.S HTML & CSS Certificates, and IT9 |  IT6 courses. Based on the devices available, the chosen open source stack, and this foundation of the team’s technical capability, the project is technically feasible within the scope.

[M3-P226] Economic Feasibility

[M3-P227] The system is an academic project, so there is no commercial development labor cost, and the use of open-source tools (Laravel, PHP, MySQL, Apache, and Visual Studio Code) keeps development expense. The system will offer operational advantages such as fewer paper records, faster access to order information, better down payment and balance tracking, organized ingredient and supply expense records, and fewer manual calculations and consolidation of records. No specific monetary savings are estimated, as reliable business data to quantify those benefits is not available. The proposed system is economically feasible within the academic project scope, as it relies on existing resources and open-source technologies, and the long-term costs would primarily depend on the hosting, domain registration, maintenance, and usage of the system.

[M3-P229] Even without commercial development costs, the system is expected to produce operational benefits like Less reliance on paper records, Faster retrieval of order details, Clearer tracking of down payments and remaining balances, Consolidated ingredient expense records, and less manual calculation and reconciliation

[M3-P230] Schedule Feasibility

[M3-P231] The project follows five phases, each tied to a stage of development

[M3-P232] Table 3: Project Phases

[M3-P233] Phase

[M3-P234] Key Activities

[M3-P235] 1. Planning & Requirements Analysis

[M3-P236] Stakeholder interviews, requirements gathering

[M3-P237] 2. Systems Architecture & Design Modeling

[M3-P238] System and database design, DFD

[M3-P239] 3. Implementation & Module Development

[M3-P240] Build order, payment, production, and reporting modules

[M3-P241] 4. Testing & Quality Assurance

[M3-P242] Functional and interface testing, revisions

[M3-P243] 5. Deployment, Training & Final Documentation

[M3-P244] Go-live, staff orientation, final documentation

[M3-P274] Chapter 3

[M3-P275] THE SOLUTION

[M3-P277] Methodology

[M3-P279] The Waterfall Model will be utilized as the software development methodology for this project. The Waterfall Model is a structured software development methodology that follows a sequential approach, in which each phase consists of specific tasks and must be completed before proceeding to the next phase (iTechGuides, 2026). The project will consist of six phases: Requirements, Design, Implementation, Integration and Testing, Deployment, and Maintenance (iTechGuides, 2026).

[M3-P280] Due to the time constraints of the project, the Waterfall Model was selected due to its structured and sequential approach that allows the development process to be systematically planned and monitored. By clearly defining the requirements and tasks for each phase, the proponents can focus on the development process and work toward completing the system within the given timeframe while minimizing major compromises to the project's intended functionality and objectives.

[M3-P283] Figure 4: The Waterfall Model

[M3-P284] Figure 4 illustrates the development process of the methodology that will be used for the proposed system. It shows the different phases involved in the development process, starting with the Requirements Phase. In the requirements phase, the proponents will analyze and verify the system requirements based on the data gathered and the initial interview conducted with the partnered business. The identified requirements will serve as the basis for determining the system’s necessary functionalities and features. This phase will be followed by the Design Phase, where the proponents will define the system’s data structure, system components, technical specifications (Day, 2025), and overall system flow (iTechGuides, 2026). Next is the Implementation Phase, where the actual development of the system will begin based on the system requirements and results of the design phase. During this phase, the proponents will develop the system’s components and functionalities according to the approved system design (iTechGuides, 2026). After the Implementation Phase, the Integration and Testing Phase will be conducted. In this phase, the developed system components will be integrated and tested to verify their functionality and satisfy the identified system requirements (iTechGuides, 2026). Any errors or issues discovered during testing will be addressed and resolved before proceeding to the next phase. This will be followed by the Deployment Phase, where the approved system will be deployed and configured within the business environment. The proponents will also conduct a system demonstration and provide the necessary training to the intended users to ensure that they understand how to properly use the system. Lastly, the Maintenance Phase will involve monitoring the deployed system for potential issues, errors, and bugs. The proponents may also implement necessary improvements, such as adding new features or functionalities. Troubleshooting support will also be provided to address issues that may arise during the system deployment. (iTechGuides, 2026)

[M3-P287] System Objectives

[M3-P304] System Requirements

[M3-P305] Hardware Requirements

[M3-P307] Hardware

[M3-P308] Minimum Specification

[M3-P309] Purpose

[M3-P310] Computer/Laptop

[M3-P311] Intel Core i3 or equivalent,4Gb Ram, 128Gb storage

[M3-P312] Main device for managing the system

[M3-P313] Smartphone

[M3-P314] Android or IOS smartphone

[M3-P315] Can be used to access the web system and communicate with  customers

[M3-P316] Internet Connection

[M3-P317] Stable internet connection

[M3-P318] Required for accessing the web-based system

[M3-P320] The proposed Order Management System with Sales Tracking and Report Generation for Aling Chona Cakes and Cupcakes requires basic hardware resources to support development, testing, deployment, and daily use. We will use a computer or laptop for system development and testing, while the owner and assistant may use a computer, laptop, or smartphone to access the web-based system. A stable internet connection is also required to allow users to access the system and communicate with the hosted application.

[M3-P322] Software Requirements

[M3-P324] Software

[M3-P325] Requirement

[M3-P326] Purpose

[M3-P327] Operating System

[M3-P328] Windows 10/11 or equivalent

[M3-P329] Runs the Development/server environment and browser

[M3-P330] Web Browser

[M3-P331] Google Chrome or Microsoft Edge

[M3-P332] Accesses the web-based system

[M3-P333] Web Server

[M3-P334] Apache

[M3-P335] Hosts and serves the web application

[M3-P336] Programming Language

[M3-P337] PHP

[M3-P338] Backend development

[M3-P339] Framework

[M3-P340] Lavavel

[M3-P341] Develops the web application

[M3-P342] Database

[M3-P343] Mysql

[M3-P344] Stores and manages customer, product, order, payment, and expense records

[M3-P345] Frontend

[M3-P346] Blade, Tailwind

[M3-P347] Creates the system interface

[M3-P348] Code Editor

[M3-P349] Visual Studio Code

[M3-P350] Used to develop and maintain the system

[M3-P351] Development Environment

[M3-P352] XAMPP

[M3-P353] Provides the local Apache server, PHP environment, and MySQL database for development and testing

[M3-P355] The proposed system will use software technologies that support web application development, database management, and system operation. Laravel and PHP will develop the application's backend and system functions, while Blade and Tailwind will create the user interface. MySQL will serve as the database for storing customer, product, order, payment, and expense records. XAMPP will provide the local Apache, PHP, and MySQL environment during development, while Visual Studio Code will be used as the development environment. A modern web browser such as Google Chrome or Microsoft Edge will be used to access the system.

[M3-P357] Entity-Relationship Diagram

[M3-P360] Figure 5: Entity-Relationship Diagram

[M3-P362] Use Case Diagram

[M3-P365] Figure 6: Use Case Diagram of Order Management System with Sales Tracking and Report Generation

[M3-P366] Figure 6 presents the Use Case Diagram of the proposed Order Management System with Sales Tracking and Report Generation for Aling Chona Cakes and Cupcakes. It illustrates the interactions between the system and its three main actors: the Owner, Assistant, and Customer. The Owner performs the system's primary administrative functions, including logging in, managing users, customers, products, and orders, recording payments and expenses, and generating reports. The Assistant supports the daily operations by updating order statuses and viewing product, customer, and payment information. The Customer interacts with the system by creating orders, making payments, and viewing order information. These use cases allow the proposed system to centralize order-related information, support payment recording, and provide organized records for business operations and reporting.

[M3-P368] Scopes and Limitations

[M3-P370] Insert text here